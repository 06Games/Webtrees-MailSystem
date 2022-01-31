<?php

declare(strict_types=1);
namespace EvanG\Modules\MailSystem;

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
            case 'api': return response($this->api($request));
            case 'html': return response($this->html($request));
            case 'send': return response($this->sendMails($request));
            default: throw new HttpNotFoundException();
        }
    }

    function api(Request $_): array
    {
        $changes = array();
        foreach($this->trees->all() as $tree) $changes[$tree->name()] = $this->getChanges($tree, 7);
        return $changes;
    }

    function html(Request $request): string
    {
        return view("{$this->module->name()}::email", [
            'subject' => "Changes",
            'items' => $this->api($request),
        ]);
    }

    function sendMails(Request $request): bool
    {
        foreach ($this->users->all() as $user) {
            if ($user->username() == "EvanG") $this->sendMail($user, "Changes", $request);
        }
        return True;
    }

    function sendMail(User $user, String $subject, Request $request){
        $html = $this->html($request);
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
                    'record' => [ 'canShow' => $record->canShow(), 'tag' => $record->tag(), 'xref' => $record->xref(), 'fullName' => $record->fullName(), 'url' => $record->url() ],
                    'time'   => Carbon::create($row->change_time)->local(),
                    'user'   => $this->users->find((int) $row->user_id)->userName(),
                ];
            })
            ->filter(static function (stdClass $row): bool { return $row->record["canShow"]; });
    }
}
