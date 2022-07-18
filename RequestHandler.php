<?php

declare(strict_types=1);

namespace EvanG\Modules\MailSystem;

use Fisharebest\Webtrees\I18N;
use Illuminate\Support\Collection;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Fisharebest\Webtrees\Exceptions\HttpNotFoundException;

// Services
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Services\EmailService;

// Mail
use Fisharebest\Webtrees\User;
use Fisharebest\Webtrees\SiteUser;
use Fisharebest\Webtrees\NoReplyUser;

// getChanges
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Expression;
use stdClass;

use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Family;

class RequestHandler implements RequestHandlerInterface
{
    public const ROUTE_PREFIX = 'mail-sys';
    protected MailSystem $module;

    protected UserService $users;
    protected TreeService $trees;
    protected EmailService $email;

    public function __construct(MailSystem $msys)
    {
        $this->module = $msys;
        $this->users = app(UserService::class);
        $this->trees = app(TreeService::class);
        $this->email = app(EmailService::class);
    }

    public function handle(Request $request): Response
    {
        switch ($request->getAttribute('action')) {
            case 'api':
                return response($this->api($this->parseRequestArgs($request)));
            case 'html':
                return response($this->html($this->parseRequestArgs($request)));
            case 'send':
                return response($this->sendMails($this->parseRequestArgs($request)));
            default:
                throw new HttpNotFoundException();
        }
    }

    function parseRequestArgs(Request $request): object
    {
        $params = $request->getQueryParams();
        $getValue = function ($param, $default, $exists = null) use ($params) {
            if (array_key_exists($param, $params)) return $exists === null ? $params[$param] : $exists($params[$param]);
            else return $default;
        };

        $strToBool = function ($str) { return $str === null || $str === "" || $str === "True"; };
        $strToInt = function ($str) { return intval($str); };
        $strToArray = function ($str) { return explode(',', $str); };

        $days = $getValue("days", 7, $strToInt);
        return (object)[
            'days' => $days,
            'title' => $getValue("title", I18N::plural('Changes in the last %s day', 'Changes in the last %s days', $days, $days)),
            'tags' => $getValue("tags", [Individual::RECORD_TYPE, Family::RECORD_TYPE], $strToArray),
            'users' => $getValue("users", [], $strToArray),
            'trees' => $getValue("trees", null, $strToArray),
            'showEmptyTrees' => $getValue("empty", true, $strToBool),
            'imageCompatibilityMode' => $getValue("png", false, $strToBool)
        ];
    }

    function api(object $args): array
    {
        $changes = [];
        foreach ($this->trees->all() as $tree)
            if ($args->trees == null || in_array($tree->name(), $args->trees)) {
                $treeChanges = $this->getChanges($tree, $args->days)
                    ->filter(static function (stdClass $row) use ($args): bool { return in_array($row->record["tag"], $args->tags); })
                    ->groupBy(static function (stdClass $row) { return Registry::timestampFactory()->fromString($row->time)->format('Y-m-d'); })
                    ->sortKeys();
                if ($args->showEmptyTrees || !$treeChanges->isEmpty()) $changes[$tree->name()] = $treeChanges;
            }
        return $changes;
    }


    function html(object $args): ?string
    {
        $items = $this->api($args);
        if (empty($items)) return null;
        return view("{$this->module->name()}::email", [
            'args' => $args,
            'subject' => $args->title,
            'items' => $items,
            'module' => $this->module
        ]);
    }

    function sendMails(object $args): array
    {
        $sent = [];
        foreach ($this->users->all() as $user) {
            if ($args->users == null || in_array($user->username(), $args->users) && $this->sendMail($user, $args)) $sent[] = $user->username();
        }
        return ["users" => $sent];
    }

    function sendMail(User $user, $args): bool
    {
        $html = $this->html($args);
        if ($html == null) return false;
        return $this->email->send(new SiteUser(), $user, new NoReplyUser(), $args->title, strip_tags($html), $html);
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
