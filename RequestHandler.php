<?php

declare(strict_types=1);

namespace EvanG\Modules\MailSystem;

use DateTimeImmutable;
use EvanG\Modules\MailSystem\Helpers\Anniversaries;
use EvanG\Modules\MailSystem\Helpers\Changes;
use EvanG\Modules\MailSystem\Helpers\Images;
use EvanG\Modules\MailSystem\Helpers\News;
use Exception;
use Fisharebest\Localization\Locale;
use Fisharebest\Localization\Translator;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\GuestUser;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Log;
use Fisharebest\Webtrees\NoReplyUser;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\CalendarService;
use Fisharebest\Webtrees\Services\EmailService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\SiteUser;
use Fisharebest\Webtrees\User;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;

class RequestHandler implements RequestHandlerInterface
{
    const ROUTE_PREFIX = "/mail-sys";

    protected MailSystem $module;
    protected array $actions;

    protected UserService $users;
    protected TreeService $trees;
    protected EmailService $email;
    protected CalendarService $calendar;

    private News $news;
    private Changes $changes;
    private Anniversaries $anniversaries;

    public function __construct(MailSystem $msys)
    {
        $this->module = $msys;
        $this->users = Registry::container()->get(UserService::class);
        $this->trees = Registry::container()->get(TreeService::class);
        $this->email = Registry::container()->get(EmailService::class);

        $this->news = new News();
        $this->changes = new Changes();
        $this->anniversaries = new Anniversaries();

        $this->actions = [
            'help' => function () { return response($this->help()); },
            'cron' => function () { return $this->cron(); },
            'get' => function () { return response($this->api($this->module->getSettings())); },
            'image' => function (Request $request) {
                if ($this->module->getSettings()->getImageDataType() != "link") return response(["message" => "Direct links are disabled"], 403);
                $loggedUser = Auth::user();
                if (!Auth::isAdmin()) {
                    Auth::login($this->users->administrators()->first());
                    Registry::cache()->array()->forget('all-trees');
                }

                $query = $request->getQueryParams();
                $record = Registry::gedcomRecordFactory()->make($query["xref"], $this->trees->find((int)$query["tree"]));
                $img = $record instanceof Individual ? Images::getImageDataResponse(Images::getIndividualPicture($record)) : null;

                if ($loggedUser instanceof GuestUser) Auth::logout();
                else Auth::login($loggedUser);
                return $img ?? response();
            },
            'html' => function (Request $request) {
                $query = $request->getQueryParams();
                return response($this->html($this->htmlData($this->module->getSettings(), $query["lang"] ?? null)));
            },
            'send' => function () { if (Auth::isAdmin()) return response($this->sendMails($this->module->getSettings())); else return null; }
        ];
    }

    private function help(): array
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

    private function cron(): Response
    {
        $settings = $this->module->getSettings();

        $today = new DateTimeImmutable("midnight");
        $nextCron = $settings->getThisSend();
        if ($today < $nextCron) return response(["message" => "Skip", "today" => $today->format("Y-m-d"), "next" => $nextCron->format("Y-m-d")]);
        $response = $this->sendMails($settings);
        $settings->setLastSend($today);
        return response($response);
    }

    private function sendMails(Settings $args): array
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

    private function sendMail(User $user, Settings $args): bool
    {
        try {
            $data = $this->htmlData($args, $user->getPreference(UserInterface::PREF_LANGUAGE), $user);
            $html = $this->html($data);
            if ($html == null) {
                Log::addErrorLog("Mail System: HTML page is null (" . $user->userName() . ")");
                return false;
            }
            return $this->email->send(new SiteUser(), $user, new NoReplyUser(), $data["subject"], strip_tags($html), $html);
        } catch (Exception $e) {
            Log::addErrorLog("Mail System: Error (" . $e->getMessage() . ")\n" . $e->getTraceAsString());
            return false;
        }
    }

    private function htmlData(Settings $args, $languageCode = null, $user = null): array
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

    public function api(Settings $args, User $user = null): array
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
                $thisCron = $args->getThisSend();
                $nextCron = $args->getNextSend();

                $treeData["dates"] = [
                    "last" => $lastCron == null ? null : $lastCron->format("Y-m-d"),
                    "this" => $thisCron->format("Y-m-d"),
                    "next" => $nextCron->format("Y-m-d")
                ];

                if ($args->getNewsEnabled()) $treeData["news"] = $this->news->get($args, $tree);
                if ($args->getChangelistEnabled()) $treeData["changes"] = $this->changes->get($args, $tree);
                if ($args->getAnniversariesEnabled()) $treeData["anniversaries"] = $this->anniversaries->get($args, $tree);

                return !$args->getEmpty() && empty($treeData->whereNotNull()->count()) ? null : $treeData;
            })->whereNotNull();
        if ($user != null) Auth::logout();
        return $data->toArray();
    }

    private function html(array $data): ?string
    {
        if (empty($data["items"])) return null;
        return view("{$this->module->name()}::email", $data);
    }

    public function handle(Request $request): Response
    {
        $action = $request->getAttribute('action');
        if (key_exists($action, $this->actions)) return $this->actions[$action]($request);
        else return redirect(route(RequestHandler::class, ['action' => 'help']));
    }
}
