<?php

declare(strict_types=1);

namespace EvanG\Modules\MailSystem;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\NoReplyUser;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\EmailService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Site;
use Fisharebest\Webtrees\SiteUser;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\User;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Services
use Fisharebest\Webtrees\Services\UserService;
use stdClass;

class RequestHandler implements RequestHandlerInterface
{
    const ROUTE_PREFIX = "/mail-sys";

    protected MailSystem $module;
    protected array $actions;

    protected UserService $users;
    protected TreeService $trees;
    protected EmailService $email;

    public function __construct(MailSystem $msys)
    {
        $this->module = $msys;
        $this->users = app(UserService::class);
        $this->trees = app(TreeService::class);
        $this->email = app(EmailService::class);

        $this->actions = [
            'help' => function () { return response($this->help()); },
            'cron' => function () { return $this->cron(); },
            'get' => function () { return response($this->api($this->module->getSettings())); },
            'html' => function () { return response($this->html($this->module->getSettings())); },
            //'send' => function () { return response($this->sendMails($this->module->getSettings())); }
        ];
    }

    public function handle(Request $request): Response
    {
        $action = $request->getAttribute('action');
        if (key_exists($action, $this->actions)) return $this->actions[$action]();
        else return redirect(route(RequestHandler::class, ['action' => 'help']));
    }

    function help(): array
    {
        $help = [];
        foreach ($this->actions as $action => $fct) {
            $help[$action] = [
                "name" => $action,
                "url" => route(RequestHandler::class, ['action' => $action])
            ];
        }
        return $help;
    }

    function cron(): Response
    {
        $settings = $this->module->getSettings();

        $lastCronTxt = Site::getPreference('EVANG_MAILSYSTEM_LASTCRONDATE');
        if(!empty($lastCronTxt)){
            $lastCronDate = new DateTimeImmutable($lastCronTxt);
            $nextCron = $lastCronDate->add(new DateInterval('P'.$settings->getDays().'D'));
            $today = new DateTime("midnight");
            if($today < $nextCron) return response(["message" => "Skip", "today" => $today, "next" => $nextCron]);
        }

        Site::setPreference('EVANG_MAILSYSTEM_LASTCRONDATE', date("Y-m-d"));
        return response($this->sendMails($settings));
    }

    function api(Settings $args): array
    {
        $changes = [];
        foreach ($this->trees->all() as $tree)
            if ($args->getTrees() == null || in_array($tree->name(), $args->getTrees())) {
                $treeChanges = $this->getChanges($tree, $args->getDays())
                    ->filter(static function (stdClass $row) use ($args): bool { return in_array($row->record["tag"], $args->getTags()); })
                    ->groupBy(static function (stdClass $row) { return Registry::timestampFactory()->fromString($row->time)->format('Y-m-d'); })
                    ->sortKeys();
                if ($args->getEmpty() || !$treeChanges->isEmpty()) $changes[$tree->name()] = $treeChanges;
            }
        return $changes;
    }


    function html(Settings $args): ?string
    {
        $items = $this->api($args);
        if (empty($items)) return null;
        return view("{$this->module->name()}::email", [
            'args' => $args,
            'subject' => I18N::plural('Changes in the last %s day', 'Changes in the last %s days', $args->getDays(), $args->getDays()),
            'items' => $items,
            'module' => $this->module
        ]);
    }

    function sendMails(Settings $args): array
    {
        $sent = [];
        foreach ($this->users->all() as $user) {
            if ($args->getUsers() == null || in_array($user->username(), $args->getUsers()) && $this->sendMail($user, $args)) $sent[] = $user->username();
        }
        return ["users" => $sent];
    }

    function sendMail(User $user, Settings $args): bool
    {
        $html = $this->html($args);
        if ($html == null) return false;
        return $this->email->send(new SiteUser(), $user, new NoReplyUser(),
            I18N::plural('Changes in the last %s day', 'Changes in the last %s days', $args->getDays(), $args->getDays()), strip_tags($html), $html);
    }

    function getChanges(Tree $tree, int $days): Collection // From getRecentChangesFromDatabase in RecentChangesModule
    {
        $subquery = DB::table('change')
            ->where('gedcom_id', '=', $tree->id())
            ->where('status', '=', 'accepted')
            ->where('new_gedcom', '<>', '')
            ->where('change_time', '>', Registry::timestampFactory()->now()->subtractDays($days)->toDateTimeString())
            ->groupBy(['xref'])
            ->select(new Expression('MAX(change_id) AS recent_change_id'));

        $query = DB::table('change')
            ->joinSub($subquery, 'recent', 'recent_change_id', '=', 'change_id')
            ->select(['change.*']);

        return $query
            ->get()
            ->map(function (stdClass $row) use ($tree): stdClass {
                $record = Registry::gedcomRecordFactory()->make($row->xref, $tree, $row->new_gedcom);
                return (object)[
                    'record' => $record == null ? null : ['canShow' => $record->canShow(), 'tag' => $record->tag(), 'xref' => $record->xref(), 'fullName' => $record->fullName(), 'url' => $record->url()],
                    'time' => $row->change_time,
                    'user' => $this->users->find((int)$row->user_id)->userName(),
                ];
            })
            ->filter(static function (stdClass $row): bool { return $row->record != null && $row->record["canShow"]; });
    }
}
