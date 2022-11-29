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

        /*** General Settings ***/
        $settings->setTrees($params->array("EVANG_MAILSYSTEM_TREES"));
        $settings->setUsers($params->array("EVANG_MAILSYSTEM_USERS"));
        $settings->setEmpty($params->integer("EVANG_MAILSYSTEM_EMPTY"));
        $settings->setDays($params->integer("EVANG_MAILSYSTEM_DAYS"));
        $settings->setImageDataType($params->string("EVANG_MAILSYSTEM_IMAGEDATA"));
        $settings->setImageFormat($params->string("EVANG_MAILSYSTEM_IMAGEFORMAT"));

        /*** News Settings ***/
        $settings->setNewsEnabled($params->integer("EVANG_MAILSYSTEM_NEWS_ENABLED"));

        /*** Change-list Settings ***/
        $settings->setChangelistEnabled($params->integer("EVANG_MAILSYSTEM_CHANGE_ENABLED"));
        $settings->setChangelistTags($params->array("EVANG_MAILSYSTEM_CHANGE_TAGS"));

        /*** Anniversaries Settings ***/
        $settings->setAnniversariesEnabled($params->integer("EVANG_MAILSYSTEM_ANNIV_ENABLED"));
        $settings->setAnniversariesDeceased($params->integer("EVANG_MAILSYSTEM_ANNIV_DECEASED"));
        $settings->setAnniversariesTags($params->array("EVANG_MAILSYSTEM_ANNIV_TAGS"));

        /*** Footer Settings ***/
        $settings->setFooterEnabled($params->string("EVANG_MAILSYSTEM_FOOTER_ENABLED"));
        $settings->setFooterMessage($params->string("EVANG_MAILSYSTEM_FOOTER_MESSAGE"));

        FlashMessages::addMessage(I18N::translate('The Mail System preferences have been updated.'), 'success');
        return redirect(route(AdminPage::class));
    }
}
