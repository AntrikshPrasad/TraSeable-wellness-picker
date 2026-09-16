<?php

namespace App\Http\Controllers;

use App\Enums\DecisionMethod;
use App\Models\WellnessSession;
use App\Support\Wheel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Random\Randomizer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deciding what happens on Friday.
 *
 * The winner is chosen here and only here. The browser is told which slice
 * won and animates to it; it never draws the result itself, or a determined
 * person with dev tools could land the wheel wherever they liked.
 */
class WellnessSessionController extends Controller
{
    public function spin(Request $request, WellnessSession $session, Randomizer $randomizer): JsonResponse
    {
        $force = $request->boolean('force');

        return DB::transaction(function () use ($session, $randomizer, $force) {
            // Lock the row for the rest of the transaction. Two people tapping
            // spin at the same moment would otherwise both read status = open
            // and both write a winner, and the second would quietly overwrite
            // the first. The loser of the race waits here, then fails the
            // isOpen() check below.
            $session = WellnessSession::whereKey($session->getKey())->lockForUpdate()->firstOrFail();

            if (! $session->isOpen()) {
                return $this->refuse('This session has already been decided.', $session);
            }

            if (! $force && ! $session->everyoneHasPicked()) {
                return $this->refuse(
                    'Not everyone has picked yet.',
                    $session,
                    ['waiting_on' => $session->usersYetToPick()->pluck('name')],
                );
            }

            $wheel = Wheel::forSession($session);

            if ($wheel->isEmpty()) {
                return $this->refuse('Nobody has picked anything yet.', $session);
            }

            $winner = $wheel->spin($randomizer);

            $session->decide($winner->activity, DecisionMethod::Spin);

            return $this->result($session, $wheel, $winner);
        });
    }

    /**
     * Re-spin because the weather turned, among the picked activities that
     * survive rain. Weights are unchanged - the outdoor slices are simply gone,
     * and the rest keep their relative sizes.
     */
    public function rainedOff(WellnessSession $session, Randomizer $randomizer): JsonResponse
    {
        return DB::transaction(function () use ($session, $randomizer) {
            $session = WellnessSession::whereKey($session->getKey())->lockForUpdate()->firstOrFail();

            if (! $session->isDecided()) {
                return $this->refuse('Only a decided session can be rained off.', $session);
            }

            $wheel = Wheel::forSession($session, weatherProofOnly: true);

            if ($wheel->isEmpty()) {
                return $this->refuse('Nobody picked anything that works indoors.', $session);
            }

            $winner = $wheel->spin($randomizer);

            $session->decide($winner->activity, DecisionMethod::Spin, rainedOff: true);

            return $this->result($session, $wheel, $winner);
        });
    }

    /**
     * Throw away the decision and put the week back to picking. Picks survive,
     * so the same wheel can be spun again.
     */
    public function reset(WellnessSession $session): JsonResponse
    {
        if ($session->isOpen()) {
            return $this->refuse('This week has not been decided yet, so there is nothing to reset.', $session);
        }

        if ($session->anotherWeekIsOpen()) {
            return $this->refuse('Another week is already open for picking. Decide or skip that one first.', $session);
        }

        if (! $session->reopen()) {
            return $this->refuse('Could not reset this week. Someone may have started another one.', $session);
        }

        return response()->json(['status' => $session->status->value]);
    }

    public function skip(WellnessSession $session): JsonResponse
    {
        if (! $session->skip()) {
            return $this->refuse('This session is already skipped.', $session);
        }

        return response()->json(['status' => $session->status->value]);
    }

    private function result(WellnessSession $session, Wheel $wheel, $winner): JsonResponse
    {
        return response()->json([
            'status' => $session->status->value,
            // The index is the whole point of this response: it tells the
            // animation which wedge to stop on.
            'winning_index' => $winner->index,
            'winning_angle' => round($winner->midAngle(), 4),
            'activity' => [
                'id' => $winner->activity->id,
                'name' => $winner->activity->name,
                'location' => $winner->activity->location->value,
                'duration_minutes' => $winner->activity->duration_minutes,
                'min_people' => $winner->activity->min_people,
            ],
            'slices' => $wheel->toArray(),
            'rained_off' => $session->wasRainedOff(),
        ]);
    }

    private function refuse(string $message, WellnessSession $session, array $extra = []): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'status' => $session->status->value,
            ...$extra,
        ], Response::HTTP_CONFLICT);
    }
}
