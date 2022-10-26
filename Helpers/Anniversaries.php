<?php

namespace EvanG\Modules\MailSystem\Helpers;

use DateTimeImmutable;
use EvanG\Modules\MailSystem\Settings;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\MediaFile;
use Fisharebest\Webtrees\Registry;
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
        return $this->calendar
            ->getEventsList(
                unixtojd($args->getThisSend()->getTimestamp()), //from
                unixtojd($args->getNextSend()->getTimestamp()), //to
                implode("|", $args->getAnniversariesTags()), !$args->getAnniversariesDeceased(), //tags
                'alpha', $tree)
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
    }

    private function getImage($individual): ?string
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

}