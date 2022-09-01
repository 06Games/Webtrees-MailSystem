<?php

declare(strict_types=1);

namespace EvanG\Modules\MailSystem;

use DateTimeImmutable;
use Fisharebest\Localization\Locale;
use Fisharebest\Localization\Translator;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\NoReplyUser;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\EmailService;
use Fisharebest\Webtrees\Services\TreeService;
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
            'html' => function (Request $request) {
                $query = $request->getQueryParams();
                return response($this->html($this->htmlData($this->module->getSettings(), $query["lang"] ?? null)));
            },
            //'send' => function () { return response($this->sendMails($this->module->getSettings())); }
        ];
    }

    public function handle(Request $request): Response
    {
        $action = $request->getAttribute('action');
        if (key_exists($action, $this->actions)) return $this->actions[$action]($request);
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

        $today = new DateTimeImmutable("midnight");
        $nextCron = $settings->getNextSend();
        if ($today < $nextCron) return response(["message" => "Skip", "today" => $today->format("Y-m-d"), "next" => $nextCron->format("Y-m-d")]);
        $settings->setLastSend($today);
        return response($this->sendMails($settings));
    }

    function api(Settings $args): array
    {
        $changes = [];
        foreach ($this->trees->all() as $tree)
            if ($args->getTrees() == null || in_array($tree->name(), $args->getTrees())) {
                $treeChanges = $this->getChanges($tree, $args->getDays())
                    ->filter(static function (stdClass $row) use ($args): bool { return in_array($row->record["tag"], $args->getTags()); })
                    ->groupBy(static function (stdClass $row) {
                        /** @noinspection PhpUndefinedMethodInspection */
                        return Registry::timestampFactory()->fromString($row->time)->format('Y-m-d');
                    })
                    ->sortKeys();
                if ($args->getEmpty() || !$treeChanges->isEmpty()) $changes[$tree->name()] = $treeChanges;
            }
        return $changes;
    }

    function htmlData(Settings $args, $languageCode = null): array
    {
        $locale = $languageCode == null ? I18N::locale() : Locale::create($languageCode);
        $translations = $this->module->customTranslations($locale->languageTag());
        $translator = new Translator($translations, $locale->pluralRule());

        $items = $this->api($args);
        return [
            'args' => $args,
            'subject' => sprintf($translator->translatePlural('Changes during the day', 'Changes during the last %s days', $args->getDays()), $args->getDays()),
            'items' => $items,
            'module' => $this->module,
            'translator' => $translator,
            'locale' => $locale
        ];
    }

    function html(array $data): ?string
    {
        if (empty($data["items"])) return null;
        return view("{$this->module->name()}::email", $data);
    }

    function sendMails(Settings $args): array
    {
        $sent = [];
        $failed = [];
        foreach ($this->users->all() as $user) {
            if ($args->getUsers() == null || in_array($user->username(), $args->getUsers())) {
                if ($this->sendMail($user, $args)) $sent[] = $user->username();
                else $failed[] = $user->username();
            }
        }
        return ["success" => $sent, "failure" => $failed];
    }

    function sendMail(User $user, Settings $args): bool
    {
        $data = $this->htmlData($args, $user->getPreference(UserInterface::PREF_LANGUAGE));
        $html = $this->html($data);
        if ($html == null) return false;
        return $this->email->send(new SiteUser(), $user, new NoReplyUser(), $data["subject"], strip_tags($html), $html);
    }

    function getChanges(Tree $tree, int $days): Collection // From getRecentChangesFromDatabase in RecentChangesModule
    {
        /** @noinspection PhpUndefinedMethodInspection */
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
