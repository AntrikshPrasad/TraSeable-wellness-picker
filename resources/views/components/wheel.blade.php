@props(['slices', 'durationMs' => 4200])

@php
    // The wheel is drawn from the same slice objects the spin endpoint uses to
    // choose a winner, so the wedge the pointer lands on is by construction the
    // one the server picked. Nothing here decides anything.
    $centre = 100;
    $radius = 92;

    // Slice 0 starts at twelve o'clock and slices run clockwise, which is what
    // the -90 turns the usual maths-convention angle into.
    $pointOnRim = function (float $degrees) use ($centre, $radius) {
        $radians = deg2rad($degrees - 90);

        return [
            round($centre + ($radius * cos($radians)), 3),
            round($centre + ($radius * sin($radians)), 3),
        ];
    };

    $isSingleSlice = count($slices) === 1;
    // Below this a label has nowhere to sit without colliding with its neighbours.
    $labelThreshold = 14;
@endphp

<div class="mx-auto w-full max-w-[320px]">
    <div class="relative">
        {{-- The pointer sits outside the rotating group, so it stays put while
             the wheel turns underneath it. --}}
        <div class="absolute left-1/2 top-0 z-10 -ml-3 -mt-1">
            <svg viewBox="0 0 24 20" class="h-5 w-6 drop-shadow" aria-hidden="true">
                <path d="M12 20 L0 0 L24 0 Z" class="fill-gray-900" />
            </svg>
        </div>

        <svg viewBox="0 0 200 200" class="w-full" role="img" aria-label="Wheel of picked activities">
            <g
                style="transform-box: fill-box; transform-origin: center;"
                class="transition-transform"
                x-bind:style="{
                    transform: `rotate(${rotation}deg)`,
                    transitionDuration: durationMs + 'ms',
                    transitionTimingFunction: 'cubic-bezier(.16,.84,.26,1)',
                }"
            >
                @foreach ($slices as $slice)
                    @php
                        [$startX, $startY] = $pointOnRim($slice->startAngle);
                        [$endX, $endY] = $pointOnRim($slice->endAngle);
                        $largeArc = $slice->sweep() > 180 ? 1 : 0;
                        $colour = $slice->activity->category->color;
                    @endphp

                    @if ($isSingleSlice)
                        {{-- An arc whose start and end coincide draws nothing, so
                             the only-one-activity case needs a plain circle. --}}
                        <circle cx="{{ $centre }}" cy="{{ $centre }}" r="{{ $radius }}" fill="{{ $colour }}" />
                    @else
                        <path
                            d="M {{ $centre }} {{ $centre }} L {{ $startX }} {{ $startY }} A {{ $radius }} {{ $radius }} 0 {{ $largeArc }} 1 {{ $endX }} {{ $endY }} Z"
                            fill="{{ $colour }}"
                            stroke="#ffffff"
                            stroke-width="1"
                        />
                    @endif
                @endforeach

                @foreach ($slices as $slice)
                    @if ($isSingleSlice || $slice->sweep() >= $labelThreshold)
                        @php
                            $mid = $slice->midAngle();
                            // Past the halfway mark the text would read upside
                            // down, so it is flipped and anchored from the rim
                            // inwards instead.
                            $flipped = $mid > 180;
                            // fmod keeps this inside 0-360. rotate(378) works
                            // but reads like a mistake in the generated markup.
                            $rotation = fmod(($flipped ? $mid + 90 : $mid - 90) + 360, 360);
                            $labelX = $flipped ? $centre - ($radius * 0.32) : $centre + ($radius * 0.32);
                            $label = Str::limit($slice->activity->name, 16);
                        @endphp

                        <text
                            transform="rotate({{ round($rotation, 3) }} {{ $centre }} {{ $centre }})"
                            x="{{ round($labelX, 3) }}"
                            y="{{ $centre }}"
                            text-anchor="{{ $flipped ? 'end' : 'start' }}"
                            dominant-baseline="middle"
                            font-size="7"
                            font-weight="600"
                            fill="#ffffff"
                            stroke="rgba(0,0,0,.35)"
                            stroke-width="1.6"
                            paint-order="stroke"
                        >{{ $label }}</text>
                    @endif
                @endforeach

                <circle cx="{{ $centre }}" cy="{{ $centre }}" r="10" fill="#ffffff" />
            </g>
        </svg>
    </div>

    {{-- A legend, because a wheel with a dozen thin wedges has nowhere to put
         half its labels. The count is what the wedge size actually means. --}}
    <ul class="mt-4 grid grid-cols-1 gap-x-4 gap-y-1 text-xs sm:grid-cols-2">
        @foreach ($slices as $slice)
            <li class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $slice->activity->category->color }}"></span>
                <span class="min-w-0 flex-1 truncate text-gray-700">{{ $slice->activity->name }}</span>
                <span class="shrink-0 tabular-nums text-gray-400">{{ $slice->weight }}</span>
            </li>
        @endforeach
    </ul>
</div>
