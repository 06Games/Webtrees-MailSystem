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
use Fisharebest\Webtrees\Carbon;
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
            case 'api': return response($this->api($this->parseRequestArgs($request)));
            case 'html': return response($this->html($this->parseRequestArgs($request)));
            case 'send': return response($this->sendMails($this->parseRequestArgs($request)));
            default: throw new HttpNotFoundException();
        }
    }

    function parseRequestArgs(Request $request): object
    {
        $params = $request->getQueryParams();
        return (object)[
            'days' => array_key_exists("days", $params) ? intval($params["days"]) : 7,
            'tags' => array_key_exists("tags", $params) ? explode(',', $params["tags"]) : [Individual::RECORD_TYPE, Family::RECORD_TYPE],
            'users' => array_key_exists("users", $params) ? explode(',', $params["users"]) : [],
            'imageCompatibilityMode' => array_key_exists("png", $params),
            'title' =>  I18N::translate('Recent changes')
        ];
    }

    function api(object $args): array
    {
        $changes = array();
        foreach($this->trees->all() as $tree)
            $changes[$tree->name()] = $this->getChanges($tree, $args->days)
                                        ->filter(static function (stdClass $row) use ($args):bool { return in_array($row->record["tag"] , $args->tags); })
                                        ->groupBy(static function (stdClass $row) { return $row->time->format('Y-m-d'); });
        return $changes;
    }

    function html(object $args): string
    {
        return view("{$this->module->name()}::email", [
            'args' =>  $args,
            'subject' => $args->title,
            'items' => $this->api($args),
            'module' => $this->module
        ]);
    }

    function sendMails(object $args): array
    {
        foreach ($this->users->all() as $user) {
            if (in_array($user->username(), $args->users)) $this->sendMail($user, $args->title, $args);
        }
        return ["users" => $args->users];
    }

    function sendMail(User $user, String $subject, $args){
        $html = $this->html($args);
        $this->email->send(new SiteUser(), $user, new NoReplyUser(), $subject, strip_tags($html), $html);
    }

    function getChanges(Tree $tree, int $days): Collection // From getRecentChangesFromDatabase in RecentChangesModule
    {
        $subquery = DB::table('change')
            ->where('gedcom_id', '=', $tree->id())
            ->where('status', '=', 'accepted')
            ->where('new_gedcom', '<>', '')
            ->where('change_time', '>', Carbon::now()->subDays($days))
            ->groupBy(['xref'])
            ->select(new Expression('MAX(change_id) AS recent_change_id'));

        $query = DB::table('change')
            ->joinSub($subquery, 'recent', 'recent_change_id', '=', 'change_id')
            ->select(['change.*']);

        return $query
            ->get()
            ->map(function (stdClass $row) use ($tree): stdClass {
                $record = Registry::gedcomRecordFactory()->make($row->xref, $tree, $row->new_gedcom);
                return (object) [
                    'record' => $record == null ? null : [ 'canShow' => $record->canShow(), 'tag' => $record->tag(), 'xref' => $record->xref(), 'fullName' => $record->fullName(), 'url' => $record->url() ],
                    'time'   => Carbon::create($row->change_time)->local(),
                    'user'   => $this->users->find((int) $row->user_id)->userName(),
                ];
            })
            ->filter(static function (stdClass $row): bool { return $row->record != null && $row->record["canShow"]; });
    }
}
