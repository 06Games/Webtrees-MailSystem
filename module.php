<?php /** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);
namespace EvanG\Modules\MailSystem;

use Aura\Router\Map;
use Aura\Router\RouterContainer;
use Composer\Autoload\ClassLoader;
use EvanG\Modules\MailSystem\Helpers\CompatibilityHelper;
use Fisharebest\Localization\Translation;
use Fisharebest\Webtrees\Http\Middleware\AuthAdministrator;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\View;

/** Sends a mail with recent changes to Webtrees users. */
class MailSystem extends AbstractModule implements ModuleCustomInterface, ModuleConfigInterface {
    use ModuleCustomTrait;

    private Settings $settings;
    public function getSettings(): Settings { return $this->settings; }

    public function __construct()
    {
        $loader = new ClassLoader();
        $loader->addPsr4('EvanG\\Modules\\MailSystem\\', __DIR__);
        $loader->register();
        $this->settings = new Settings();
    }

    public function boot(): void
    {
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');

        /** @var RouterContainer $router */ $router = CompatibilityHelper::getService(RouterContainer::class);

        $map = $router->getMap();
        $module = $this;
        $map->get(RequestHandler::class, RequestHandler::ROUTE_PREFIX . '/{action}', new RequestHandler($module));

        $map->attach('', '/admin', static function (Map $router) use ($module) {
            $router->extras([
                'middleware' => [
                    AuthAdministrator::class,
                ],
            ]);
            $router->get(AdminPage::class, '/mail-sys', new AdminPage($module));
            $router->get(AdminAction::class, '/mail-sys/save', new AdminAction($module));
        });
    }

    public function title(): string { return 'Mail System'; }
    public function description(): string { return I18N::translate('Sends out newsletters at regular intervals'); }
    public function customModuleAuthorName(): string { return 'EvanG'; }
    public function customModuleSupportUrl(): string { return 'https://github.com/06Games/Webtrees-MailSystem'; }
    public function customModuleVersion(): string { return '2.3.9'; }
    public function customModuleLatestVersionUrl(): string { return 'https://github.com/06Games/Webtrees-MailSystem/raw/main/version.txt'; }
    public function resourcesFolder(): string { return __DIR__ . '/resources/'; }
    public function getConfigLink(): string { return route(AdminPage::class); }
    public function customTranslations(string $language): array
    {
        $languageFile = $this->resourcesFolder() . 'lang/' . $language . '.mo';
        return file_exists($languageFile) ? (new Translation($languageFile))->asArray() : [];
    }
}
return new MailSystem();
