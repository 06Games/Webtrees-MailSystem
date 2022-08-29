<?php

declare(strict_types=1);

namespace EvanG\Modules\MailSystem;

use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AdminAction implements RequestHandlerInterface
{
    protected MailSystem $module;

    public function __construct(MailSystem $msys) { $this->module = $msys; }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = Validator::queryParams($request);
        $settings = $this->module->getSettings();

        $settings->setTrees($params->array("EVANG_MAILSYSTEM_TREES"));
        $settings->setUsers($params->array("EVANG_MAILSYSTEM_USERS"));
        $settings->setTags($params->array("EVANG_MAILSYSTEM_TAGS"));
        $settings->setEmpty($params->integer("EVANG_MAILSYSTEM_EMPTY"));
        $settings->setDays($params->integer("EVANG_MAILSYSTEM_DAYS"));
        $settings->setImageFormat($params->string("EVANG_MAILSYSTEM_IMAGEFORMAT"));

        FlashMessages::addMessage(I18N::translate('The Mail System preferences have been updated.'), 'success');
        return redirect(route(AdminPage::class));
    }
}
