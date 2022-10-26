<?php

declare(strict_types=1);

namespace EvanG\Modules\MailSystem;

use DateTimeImmutable;
use Fisharebest\Localization\Locale;
use Fisharebest\Localization\Translator;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Log;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\MediaFile;
use Fisharebest\Webtrees\NoReplyUser;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\CalendarService;
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
    protected CalendarService $calendar;

    public function __construct(MailSystem $msys)
    {
        $this->module = $msys;
        $this->users = app(UserService::class);
        $this->trees = app(TreeService::class);
        $this->email = app(EmailService::class);
        $this->calendar = app(CalendarService::class);

        $this->actions = [
            'help' => function () { return response($this->help()); },
            'cron' => function () { return $this->cron(); },
            'get' => function () { return response($this->api($this->module->getSettings())); },
            'html' => function (Request $request) {
                $query = $request->getQueryParams();
                return response($this->html($this->htmlData($this->module->getSettings(), $query["lang"] ?? null)));
            },
            'send' => function () { if (Auth::isAdmin()) return response($this->sendMails($this->module->getSettings())); else return null; }
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
        $endpoints = [];
        foreach ($this->actions as $action => $fct) {
            $endpoints[$action] = [
                "name" => $action,
                "url" => route(RequestHandler::class, ['action' => $action])
            ];
        }
        return [
            "version" => $this->module->customModuleVersion(),
            "latest_version" => $this->module->customModuleLatestVersion(),
            "update_available" => version_compare($this->module->customModuleLatestVersion(), $this->module->customModuleVersion()) > 0,
            "endpoints" => $endpoints
        ];
    }

    function cron(): Response
    {
        $settings = $this->module->getSettings();

        $today = new DateTimeImmutable("midnight");
        $nextCron = $settings->getNextSend();
        if ($today < $nextCron) return response(["message" => "Skip", "today" => $today->format("Y-m-d"), "next" => $nextCron->format("Y-m-d")]);
        $response = $this->sendMails($settings);
        $settings->setLastSend($today);
        return response($response);
    }

    function api(Settings $args, User $user = null): array
    {
        if ($user != null) {
            Auth::login($user);
            Registry::cache()->array()->forget('all-trees');
        }

        $data = $this->trees->all()
            ->filter(function ($tree) use ($args) {
                return $args->getTrees() == null || in_array($tree->name(), $args->getTrees());
            })->map(function ($tree) use ($args) {
                $treeData = new Collection();

                $lastCron = $args->getLastSend();
                $thisCron = $args->getNextSend();
                $nextCron = $args->getNextSend()->add(new \DateInterval("P" . $args->getDays() . "D"));

                $treeData["dates"] = ["last" => $lastCron->format("Y-m-d"), "this" => $thisCron->format("Y-m-d"), "next" => $nextCron->format("Y-m-d")];

                if ($args->getChangelistEnabled())
                    $treeData["changes"] = $this->getChanges($tree, $lastCron, $thisCron)
                        ->filter(static fn(stdClass $row) => in_array($row->record["tag"], $args->getChangelistTags()))
                        ->groupBy(static fn(stdClass $row) => (new DateTimeImmutable($row->time))->format('Y-m-d'))
                        ->sortKeys();

                if ($args->getAnniversariesEnabled())
                    $treeData["anniversaries"] = $this->calendar
                        ->getEventsList(unixtojd($thisCron->getTimestamp()), unixtojd($nextCron->getTimestamp()), implode("|", $args->getAnniversariesTags()), !$args->getAnniversariesDeceased(), 'alpha', $tree)
                        ->map(function (Fact $fact) {
                            $date = new DateTimeImmutable(jdtogregorian($fact->date()->julianDay()));
                            return [
                                "tag" => $fact->tag(),
                                "xref" => $fact->record()->xref(),
                                "id" => $fact->id(),
                                "name" => $fact->record()->fullName(),
                                "date" => $date->format("Y-m-d"),
                                "age" => date("Y") - $date->format("Y"),
                                "url" => $fact->record()->url(),
                                "picture" => $this->getImage($fact->record())
                            ];
                        })->groupBy(static fn($fact) => (new DateTimeImmutable($fact["date"]))->format('-m-d'))
                        ->sortKeys();

                return !$args->getEmpty() && empty($treeData->whereNotNull()->count()) ? null : $treeData;
            })->whereNotNull();
        if ($user != null) Auth::logout();
        return $data->toArray();
    }

    private function getImage($individual)
    {
        foreach ($individual->facts(['OBJE']) as $fact) {
            $media_object = $fact->target();
            if ($media_object instanceof Media) {
                $media_file = $media_object->firstImageFile();
                if ($media_file instanceof MediaFile) {
                    $image_factory = Registry::imageFactory();
                    $response = $image_factory->mediaFileThumbnailResponse(
                        $media_file,
                        50,
                        50,
                        "crop",
                        false
                    );
                    return 'data: ' . $media_file->mimeType() . ';base64,' . base64_encode((string)$response->getBody());
                }
            }
        }
        return null;
    }

    function htmlData(Settings $args, $languageCode = null, $user = null): array
    {
        $locale = empty($languageCode) ? I18N::locale() : Locale::create($languageCode);
        $translations = $this->module->customTranslations($locale->languageTag());
        $translator = new Translator($translations, $locale->pluralRule());

        $items = $this->api($args, $user);
        return [
            'args' => $args,
            'subject' => $translator->translate('Newsletter'),
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
        $data = $this->htmlData($args, $user->getPreference(UserInterface::PREF_LANGUAGE), $user);
        $html = $this->html($data);
        if ($html == null) {
            Log::addErrorLog("Mail System: HTML page is null (" . $user->userName() . ")");
            return false;
        }
        return $this->email->send(new SiteUser(), $user, new NoReplyUser(), $data["subject"], strip_tags($html), $html);
    }

    function getChanges(Tree $tree, DateTimeImmutable $start, DateTimeImmutable $end): Collection // From getRecentChangesFromDatabase in RecentChangesModule
    {
        $subquery = DB::table('change')
            ->where('gedcom_id', '=', $tree->id())
            ->where('status', '=', 'accepted')
            ->where('new_gedcom', '<>', '')
            ->where('change_time', '>', $start->format("Y-m-d H:i:s"))
            ->where('change_time', '<', $end->format("Y-m-d H:i:s"))
            ->groupBy(['xref'])
            ->select(new Expression('MAX(change_id) AS recent_change_id'));

        $query = DB::table('change')
            ->joinSub($subquery, 'recent', 'recent_change_id', '=', 'change_id')
            ->select(['change.*']);

        return $query
            ->get()
            ->map(function (stdClass $row) use ($tree): ?stdClass {
                $record = Registry::gedcomRecordFactory()->make($row->xref, $tree, $row->new_gedcom);
                if ($record == null || !$record->canShow()) return null;
                return (object)[
                    'record' => [
                        'tag' => $record->tag(),
                        'xref' => $record->xref(),
                        'fullName' => $record->fullName(),
                        'url' => $record->url()
                    ],
                    'time' => $row->change_time,
                    'user' => $this->users->find((int)$row->user_id)->userName(),
                ];
            })
            ->filter(static function ($row): bool { return $row != null; });
    }
}
