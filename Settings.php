<?php

namespace EvanG\Modules\MailSystem;

use DateInterval;
use DateTimeImmutable;
use Exception;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Location;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\Note;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Repository;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Site;
use Fisharebest\Webtrees\Source;
use Fisharebest\Webtrees\Submitter;

class Settings
{
    private UserService $userService;
    private TreeService $treeService;

    private array $allTrees;
    private array $allUsers;
    private array $allImageFormat;

    public function __construct()
    {
        $this->userService = Registry::container()->get(UserService::class);
        $this->treeService = Registry::container()->get(TreeService::class);

        $users = [];
        foreach ($this->userService->all() as $u) $users[$u->username()] = $u->username();
        $this->allUsers = $users;

        $trees = [];
        foreach ($this->treeService->all() as $t) $trees[$t->name()] = $t->name();
        $this->allTrees = $trees;

        $formats = [];
        foreach (["png", "svg"] as $f) $formats[$f] = $f;
        $this->allImageFormat = $formats;
    }

    #region General

    public function getUsers(): array
    {
        $pref = Site::getPreference('EVANG_MAILSYSTEM_USERS');
        if (empty($pref)) return $this->getAllUsers();
        return explode(",", $pref);
    }

    public function getAllUsers(): array { return $this->allUsers; }

    public function setUsers($value) { Site::setPreference('EVANG_MAILSYSTEM_USERS', implode(',', $value)); }

    public function getTrees(): array
    {
        $pref = Site::getPreference('EVANG_MAILSYSTEM_TREES');
        if (empty($pref)) return $this->getAllTrees();
        return explode(",", $pref);
    }

    public function getAllTrees(): array { return $this->allTrees; }

    public function setTrees($value) { Site::setPreference('EVANG_MAILSYSTEM_TREES', implode(',', $value)); }


    public function getEmpty(): bool
    {
        $pref = Site::getPreference('EVANG_MAILSYSTEM_EMPTY');
        if (empty($pref)) return false;
        return (bool)$pref;
    }

    public function setEmpty($value) { Site::setPreference('EVANG_MAILSYSTEM_EMPTY', $value); }

    public function setDays($value) { Site::setPreference('EVANG_MAILSYSTEM_DAYS', $value); }

    public function getAllImageDataType(): array
    {
        return [
            "link" => I18N::translate("Direct URLs"),
            "data" => I18N::translate("Data URLs"),
            "none" => I18N::translate("No images")
        ];
    }

    public function getImageDataType(): string
    {
        $pref = Site::getPreference('EVANG_MAILSYSTEM_IMAGEDATA');
        if (empty($pref)) return "data";
        return $pref;
    }

    public function setImageDataType($value) { Site::setPreference('EVANG_MAILSYSTEM_IMAGEDATA', $value); }

    public function getAllImageFormat(): array { return $this->allImageFormat; }

    public function getImageFormat(): string
    {
        $pref = Site::getPreference('EVANG_MAILSYSTEM_IMAGEFORMAT');
        if (empty($pref)) return "png";
        return $pref;
    }

    public function setImageFormat($value) { Site::setPreference('EVANG_MAILSYSTEM_IMAGEFORMAT', $value); }

    #endregion


    #region News

    public function getNewsEnabled(): bool
    {
        $pref = Site::getPreference('EVANG_MAILSYSTEM_NEWS_ENABLED');
        if (!isset($pref)) return true;
        return (bool)$pref;
    }

    public function setNewsEnabled($value) { Site::setPreference('EVANG_MAILSYSTEM_NEWS_ENABLED', $value); }

    #endregion


    #region Change-list

    public function getChangelistEnabled(): bool
    {
        $pref = Site::getPreference('EVANG_MAILSYSTEM_CHANGE_ENABLED');
        if (!isset($pref)) return true;
        return (bool)$pref;
    }

    public function setChangelistEnabled($value) { Site::setPreference('EVANG_MAILSYSTEM_CHANGE_ENABLED', $value); }

    public function getAllChangelistTags(): array
    {
        return [
            Individual::RECORD_TYPE => I18N::translate("Individual"),
            Family::RECORD_TYPE => I18N::translate("Family"),
            Media::RECORD_TYPE => I18N::translate("Media"),
            Note::RECORD_TYPE => I18N::translate("Note"),
            Source::RECORD_TYPE => I18N::translate("Source"),
            Submitter::RECORD_TYPE => I18N::translate("Submitter"),
            Repository::RECORD_TYPE => I18N::translate("Repository"),
            Location::RECORD_TYPE => I18N::translate('Location')];
    }

    public function getChangelistTags(): array
    {
        $pref = Site::getPreference('EVANG_MAILSYSTEM_CHANGE_TAGS');
        if (empty($pref)) return [Individual::RECORD_TYPE, Family::RECORD_TYPE];
        return explode(",", $pref);
    }

    public function setChangelistTags($value) { Site::setPreference('EVANG_MAILSYSTEM_CHANGE_TAGS', implode(',', $value)); }

    #endregion

    #region Anniversaries

    public function getAnniversariesEnabled(): bool
    {
        $pref = Site::getPreference('EVANG_MAILSYSTEM_ANNIV_ENABLED');
        if (!isset($pref)) return true;
        return (bool)$pref;
    }

    public function setAnniversariesEnabled($value) { Site::setPreference('EVANG_MAILSYSTEM_ANNIV_ENABLED', $value); }

    public function getAnniversariesDeceased(): bool
    {
        $pref = Site::getPreference('EVANG_MAILSYSTEM_ANNIV_DECEASED');
        if (!isset($pref)) return false;
        return (bool)$pref;
    }

    public function setAnniversariesDeceased($value) { Site::setPreference('EVANG_MAILSYSTEM_ANNIV_DECEASED', $value); }

    public function getAllAnniversariesTags(): array
    {
        $data = [];
        foreach (array_merge(Gedcom::BIRTH_EVENTS, Gedcom::DEATH_EVENTS) as $tag) $data[$tag] = Registry::elementFactory()->make("INDI:" . $tag)->label();
        foreach (array_merge(Gedcom::MARRIAGE_EVENTS) as $tag) $data[$tag] = Registry::elementFactory()->make("FAM:" . $tag)->label();
        return $data;
    }

    public function getAnniversariesTags(): array
    {
        $pref = Site::getPreference('EVANG_MAILSYSTEM_ANNIV_TAGS');
        if (empty($pref)) return ["BIRT"];
        return explode(",", $pref);
    }

    public function setAnniversariesTags($value) { Site::setPreference('EVANG_MAILSYSTEM_ANNIV_TAGS', implode(',', $value)); }

    #endregion


    #region Infos

    public function setLastSend(?DateTimeImmutable $date)
    {
        Site::setPreference('EVANG_MAILSYSTEM_LASTCRONDATE', $date == null ? "" : $date->format("Y-m-d"));
    }

    public function getNextSend(): DateTimeImmutable
    {
        try {
            return $this->getThisSend()->add(new DateInterval("P" . $this->getDays() . "D"));
        } catch (Exception $e) {
            return $this->getThisSend();
        }
    }

    public function getThisSend(): DateTimeImmutable
    {
        $lastCronDate = $this->getLastSend();
        $today = new DateTimeImmutable("midnight");
        if ($lastCronDate == null) return $today;
        try {
            return $lastCronDate->add(new DateInterval('P' . $this->getDays() . 'D'));
        } catch (Exception $e) {
            return $today;
        }
    }

    public function getLastSend(): ?DateTimeImmutable
    {
        $lastCronTxt = Site::getPreference('EVANG_MAILSYSTEM_LASTCRONDATE');
        if (empty($lastCronTxt)) return null;
        try {
            return new DateTimeImmutable($lastCronTxt);
        } catch (Exception $e) {
            /* The date of the last send couldn't be understood, so we send the mails to give it a known value */
            return null;
        }
    }

    public function getDays(): int
    {
        $pref = Site::getPreference('EVANG_MAILSYSTEM_DAYS');
        if (empty($pref)) return 7;
        return (int)$pref;
    }

    #endregion


    #region Footer

    public function getFooterEnabled(): string
    {
        $pref = Site::getPreference('EVANG_MAILSYSTEM_FOOTER_ENABLED');
        if (!isset($pref)) return true;
        return $pref;
    }

    public function setFooterEnabled($value) { Site::setPreference('EVANG_MAILSYSTEM_FOOTER_ENABLED', $value); }

    public function getFooterMessage(): string
    {
        $pref = Site::getPreference('EVANG_MAILSYSTEM_FOOTER_MESSAGE');
        if (empty($pref)) return I18N::translate("This email was autogenerated by Webtrees and programmed with ❤ by %s",
            "<a href=\"https://github.com/06Games/Webtrees-MailSystem\">Evan Galli</a>");
        return $pref;
    }

    public function setFooterMessage($value) { Site::setPreference('EVANG_MAILSYSTEM_FOOTER_MESSAGE', $value); }

    #endregion
}
