<?php

namespace EvanG\Modules\MailSystem;

use Fisharebest\Webtrees\Http\ViewResponseTrait;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Services\UserService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Edit the site preferences.
 */
class AdminPage implements RequestHandlerInterface
{
    use ViewResponseTrait;

    protected UserService $userService;
    protected TreeService $treeService;

    protected MailSystem $module;

    public function __construct(MailSystem $msys)
    {
        $this->module = $msys;
        $this->userService = Registry::container()->get(UserService::class);
        $this->treeService = Registry::container()->get(TreeService::class);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $settings = $this->module->getSettings();
        $this->layout = 'layouts/administration';

        $title = I18N::translate('Mail system preferences');

        return $this->viewResponse($this->module->name() . '::admin', [
            'title' => $title,
            'settings' => $settings
        ]);
    }
}