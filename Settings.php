<?php

namespace EvanG\Modules\MailSystem;

use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\Note;
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
        $this->userService = app(UserService::class);
        $this->treeService = app(TreeService::class);

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

    public function getAllUsers(): array { return $this->allUsers; }

    public function getUsers(): array
    {
        $pref = Site::getPreference('EVANG_MAILSYSTEM_USERS');
        if (empty($pref)) return $this->getAllUsers();
        return explode(",", $pref);
    }

    public function setUsers($value) { Site::setPreference('EVANG_MAILSYSTEM_USERS', implode(',', $value)); }


    public function getAllTrees(): array { return $this->allTrees; }

    public function getTrees(): array
    {
        $pref = Site::getPreference('EVANG_MAILSYSTEM_TREES');
        if (empty($pref)) return $this->getAllTrees();
        return explode(",", $pref);
    }

    public function setTrees($value) { Site::setPreference('EVANG_MAILSYSTEM_TREES', implode(',', $value)); }


    public function getEmpty(): bool
    {
        $pref = Site::getPreference('EVANG_MAILSYSTEM_EMPTY');
        if (empty($pref)) return false;
        return (bool)$pref;
    }

    public function setEmpty($value) { Site::setPreference('EVANG_MAILSYSTEM_EMPTY', $value); }


    public function getDays(): int
    {
        $pref = Site::getPreference('EVANG_MAILSYSTEM_DAYS');
        if (empty($pref)) return 7;
        return (int)$pref;
    }

    public function setDays($value) { Site::setPreference('EVANG_MAILSYSTEM_DAYS', $value); }


    public function getAllImageFormat(): array { return $this->allImageFormat; }

    public function getImageFormat(): string
    {
        $pref = Site::getPreference('EVANG_MAILSYSTEM_IMAGEFORMAT');
        if (empty($pref)) return "png";
        return $pref;
    }

    public function setImageFormat($value) { Site::setPreference('EVANG_MAILSYSTEM_IMAGEFORMAT', $value); }


    public function getAllTags(): array
    {
        return [
            Individual::RECORD_TYPE => I18N::translate("Individual"),
            Family::RECORD_TYPE => I18N::translate("Family"),
            Media::RECORD_TYPE => I18N::translate("Media"),
            Note::RECORD_TYPE => I18N::translate("Note"),
            Source::RECORD_TYPE => I18N::translate("Source"),
            Submitter::RECORD_TYPE => I18N::translate("Submitter"),
            Repository::RECORD_TYPE => I18N::translate("Repository")];
    }

    public function getTags(): array
    {
        $pref = Site::getPreference('EVANG_MAILSYSTEM_TAGS');
        if (empty($pref)) return [Individual::RECORD_TYPE, Family::RECORD_TYPE];
        return explode(",", $pref);
    }

    public function setTags($value) { Site::setPreference('EVANG_MAILSYSTEM_TAGS', implode(',', $value)); }
}