<?php

namespace EvanG\Modules\MailSystem\Helpers;

use DateTimeImmutable;
use EvanG\Modules\MailSystem\Settings;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Services\CalendarService;
use Fisharebest\Webtrees\Tree;
use Illuminate\Support\Collection;

class Anniversaries implements DataGetter
{
    protected CalendarService $calendar;

    public function __construct()
    {
        $this->calendar = app(CalendarService::class);
    }

    public function get(Settings $args, Tree $tree): Collection
    {
        $imgSource = $args->getImageDataType();

        return $this->calendar
            ->getEventsList(
                unixtojd($args->getThisSend()->getTimestamp()), //from
                unixtojd($args->getNextSend()->getTimestamp()), //to
                implode("|", $args->getAnniversariesTags()), !$args->getAnniversariesDeceased(), //tags
                'alpha', $tree)
            ->map(function (Fact $fact) use ($imgSource) {
                $date = new DateTimeImmutable(jdtogregorian($fact->date()->julianDay()));
                $record = $fact->record();

                $img = [];
                $individuals = [];

                if ($record instanceof Individual) $individuals[] = $record;
                else if ($record instanceof Family) $individuals = $record->spouses()->toArray();

                foreach ($individuals as $individual)
                    if ($individual instanceof Individual) {
                        if ($imgSource == "data") $img[] = Images::getImageDataUrl(Images::getIndividualPicture($individual));
                        else if ($imgSource == "link") $img[] = Images::getImageDirectUrl($individual);
                    }

                return [
                    "tag" => $fact->tag(),
                    "xref" => $record->xref(),
                    "id" => $fact->id(),
                    "name" => $record->fullName(),
                    "date" => $date->format("Y-m-d"),
                    "age" => date("Y") - $date->format("Y"),
                    "url" => $record->url(),
                    "picture" => $img
                ];
            })->groupBy(static fn($fact) => (new DateTimeImmutable($fact["date"]))->format('-m-d'))
            ->sortKeys();
    }
}