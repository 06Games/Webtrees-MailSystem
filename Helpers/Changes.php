<?php

namespace EvanG\Modules\MailSystem\Helpers;

use DateInterval;
use DateTimeImmutable;
use EvanG\Modules\MailSystem\Settings;
use Exception;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Tree;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use stdClass;

class Changes implements DataGetter
{
    protected UserService $users;

    public function __construct()
    {
        $this->users = app(UserService::class);
    }

    /**
     * @throws Exception
     */
    public function get(Settings $args, Tree $tree): Collection
    {
        $startDate = $args->getLastSend() ?? $args->getThisSend()->sub(new DateInterval("P" . $args->getDays() . "D"));
        return $this->getChanges($tree, $startDate, $args->getThisSend())
            ->map(function (stdClass $row) use ($tree): ?object {
                $record = Registry::gedcomRecordFactory()->make($row->xref, $tree, $row->new_gedcom);
                if ($record == null || !$record->canShow()) return null;
                return (object)[
                    'record' => [
                        'tag' => $record->tag(),
                        'xref' => $record->xref(),
                        'fullName' => $record->fullName(),
                        'url' => $record->url()
                    ],
                    'time' => $row->change_time,
                    'user' => $this->users->find((int)$row->user_id)->userName(),
                ];
            })
            ->filter(static fn($row) => $row != null && in_array($row->record["tag"], $args->getChangelistTags()))
            ->groupBy(static fn($row) => (new DateTimeImmutable($row->time))->format('Y-m-d'))
            ->sortKeys();
    }

    function getChanges(Tree $tree, DateTimeImmutable $start, DateTimeImmutable $end): Collection // From getRecentChangesFromDatabase in RecentChangesModule
    {
        $subquery = DB::table('change')
            ->where('gedcom_id', '=', $tree->id())
            ->where('status', '=', 'accepted')
            ->where('new_gedcom', '<>', '')
            ->where('change_time', '>', $start->format("Y-m-d H:i:s"))
            ->where('change_time', '<', $end->format("Y-m-d H:i:s"))
            ->groupBy(['xref'])
            ->select(new Expression('MAX(change_id) AS recent_change_id'));

        $query = DB::table('change')
            ->joinSub($subquery, 'recent', 'recent_change_id', '=', 'change_id')
            ->select(['change.*']);

        return $query->get();
    }
}