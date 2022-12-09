<?php

namespace EvanG\Modules\MailSystem\Helpers;

use DateInterval;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use EvanG\Modules\MailSystem\Settings;
use Exception;
use Fisharebest\Webtrees\Tree;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;
use stdClass;

class News implements DataGetter
{
    /**
     * @throws Exception
     */
    public function get(Settings $args, Tree $tree): Collection
    {
        $minDate = $args->getLastSend() ?? $args->getThisSend()->sub(new DateInterval('P' . $args->getDays() . 'D'));
        return DB::table('news')
            ->where('gedcom_id', '=', $tree->id())
            ->where('updated', '>', $minDate->format("Y-m-d H:i:s"))
            ->where('updated', '<', $args->getThisSend()->format("Y-m-d H:i:s"))
            ->orderBy('updated')
            ->get()
            ->map(function (stdClass $row) {
                return [
                    "id" => $row->news_id,
                    "date" => (new DateTime($row->updated, new DateTimeZone("UTC")))->format(DateTimeInterface::ATOM),
                    "subject" => $row->subject,
                    "body" => $row->body
                ];
            });
    }
}