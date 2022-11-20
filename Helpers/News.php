<?php

namespace EvanG\Modules\MailSystem\Helpers;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use EvanG\Modules\MailSystem\Settings;
use Fisharebest\Webtrees\Tree;
use Illuminate\Support\Collection;
use Illuminate\Database\Capsule\Manager as DB;
use stdClass;

class News implements DataGetter
{
    public function get(Settings $args, Tree $tree): Collection
    {
        return DB::table('news')
            ->where('gedcom_id', '=', $tree->id())
            ->where('updated', '>', $args->getLastSend()->format("Y-m-d H:i:s"))
            ->where('updated', '<', $args->getThisSend()->format("Y-m-d H:i:s"))
            ->orderBy('updated')
            ->get()
            ->map(function (stdClass $row){
                return [
                    "id" => $row->news_id,
                    "date" => (new DateTime($row->updated, new DateTimeZone("UTC")))->format(DateTimeInterface::ATOM),
                    "subject" => $row->subject,
                    "body" => $row->body
                ];
            });
    }
}