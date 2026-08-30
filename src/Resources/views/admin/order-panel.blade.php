@inject('sendificoShipments', 'Tapstar\Sendifico\Repositories\ShipmentRepository')
@inject('sendificoTerritories', 'Tapstar\Sendifico\Services\TerritoryService')
@inject('sendificoClient', 'Tapstar\Sendifico\Api\Client')

@php
    $sendificoRow = $sendificoShipments->findOneWhere(['order_id' => $order->id]);

    $sendificoStatusGroup = $sendificoRow?->shipment_id
        ? \Tapstar\Sendifico\Shipment\ShipmentStatus::group($sendificoRow->status)
        : null;

    $sendificoEditable = ! $sendificoRow || (! $sendificoRow->is_paid && ! $sendificoRow->is_terminal);

    $sendificoAddress = $order->shipping_address ?: $order->billing_address;

    $sendificoCurrentTerritory = $sendificoRow?->territory_base_id
        ? $sendificoTerritories->all()->firstWhere('territory_base_id', $sendificoRow->territory_base_id)?->searchable_text
        : null;

    $sendificoTerritoryOptions = $sendificoTerritories->all()
        ->map(fn ($territory) => ['id' => $territory->territory_base_id, 'text' => $territory->searchable_text])
        ->values();
@endphp

@if ($sendificoClient->hasApiKey() || $sendificoRow)
    <div class="box-shadow rounded bg-white p-4 dark:bg-gray-900">
        <div class="flex items-center justify-between gap-2">
            <p class="text-base font-semibold text-gray-800 dark:text-white">
                @lang('sendifico::app.admin.panel.title')
            </p>

            @if ($sendificoRow?->shipment_id)
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-gray-800 dark:text-slate-300">
                    {{ $sendificoStatusGroup ? trans('sendifico::app.admin.panel.group.'.$sendificoStatusGroup) : $sendificoRow->status }}
                    ({{ $sendificoRow->status }})
                </span>
            @endif
        </div>

        @if ($sendificoRow?->last_error)
            <p class="mt-2 text-sm text-red-600">
                {{ $sendificoRow->last_error }}
            </p>
        @endif

        {{-- Shipment facts, once one exists --}}
        @if ($sendificoRow?->shipment_id)
            <div class="mt-2 grid gap-1 text-sm text-gray-600 dark:text-gray-300">
                <p>@lang('sendifico::app.admin.panel.shipment-id'): {{ $sendificoRow->shipment_id }}</p>

                @if ($sendificoRow->carrier_token)
                    <p>@lang('sendifico::app.admin.panel.carrier'): {{ $sendificoRow->carrier_token }} — ${{ number_format((float) $sendificoRow->price_total, 2) }}</p>
                @endif

                @if ((float) $sendificoRow->goods_collection > 0)
                    <p>@lang('sendifico::app.admin.panel.cod'): ${{ number_format((float) $sendificoRow->goods_collection, 2) }}</p>
                @endif

                @if ($sendificoRow->tracking_number)
                    <p>
                        @lang('sendifico::app.admin.panel.tracking'):

                        @if ($sendificoRow->tracking_carrier_url)
                            <a
                                href="{{ $sendificoRow->tracking_carrier_url }}"
                                target="_blank"
                                class="text-blue-600"
                            >{{ $sendificoRow->tracking_number }}</a>
                        @else
                            {{ $sendificoRow->tracking_number }}
                        @endif
                    </p>
                @endif

                @if ((int) $sendificoRow->incident_count > 0)
                    <p class="font-semibold text-red-600">
                        @lang('sendifico::app.admin.panel.incidents', ['count' => $sendificoRow->incident_count])
                    </p>
                @endif
            </div>
        @endif

        {{-- Destination: address summary, territory, and corrections. One
             section rather than scattered steps — everything about where the
             parcel goes lives here and stays editable until purchase. --}}
        @if ($sendificoEditable && $sendificoAddress)
            <div class="mt-4 border-t pt-3 dark:border-gray-800">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    @lang('sendifico::app.admin.panel.section-destination')
                </p>

                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    {{ trim($sendificoAddress->first_name.' '.$sendificoAddress->last_name) }}
                    · {{ str_replace(["\r\n", "\n"], ', ', trim((string) $sendificoAddress->address)) }}
                    · {{ $sendificoAddress->city }}, {{ $sendificoAddress->state }}
                    · {{ $sendificoAddress->phone }}
                </p>

                @if (! $sendificoRow)
                    <form
                        method="POST"
                        action="{{ route('admin.sendifico.draft', $order->id) }}"
                        class="mt-3"
                    >
                        @csrf

                        <button type="submit" class="secondary-button">
                            @lang('sendifico::app.admin.panel.create-draft')
                        </button>
                    </form>
                @else
                    @if (! $sendificoRow->shipment_id)
                        <p class="mt-2 text-sm text-amber-700 dark:text-amber-500">
                            @lang('sendifico::app.admin.panel.territory-help', ['city' => $sendificoAddress->city])
                        </p>
                    @endif

                    <form
                        method="POST"
                        action="{{ route('admin.sendifico.territory', $order->id) }}"
                        class="mt-2 flex flex-wrap items-center gap-2"
                    >
                        @csrf

                        <v-sendifico-territory-select
                            :territories='@json($sendificoTerritoryOptions)'
                            initial-id="{{ $sendificoRow->territory_base_id }}"
                            initial-text="{{ $sendificoCurrentTerritory }}"
                            placeholder="@lang('sendifico::app.admin.panel.territory-placeholder')"
                        ></v-sendifico-territory-select>

                        <button type="submit" class="secondary-button">
                            @lang('sendifico::app.admin.panel.update-destination')
                        </button>
                    </form>
                @endif

                <details class="mt-2">
                    <summary class="cursor-pointer text-sm font-medium text-blue-600 dark:text-blue-400">
                        @lang('sendifico::app.admin.panel.address.title')
                    </summary>

                    <form
                        method="POST"
                        action="{{ route('admin.sendifico.address', $order->id) }}"
                        class="mt-3 grid max-w-[440px] grid-cols-2 gap-2"
                    >
                        @csrf

                        @foreach (['first_name' => 'first-name', 'last_name' => 'last-name'] as $field => $label)
                            <label class="grid gap-1 text-xs text-gray-600 dark:text-gray-300">
                                @lang('sendifico::app.admin.panel.address.'.$label)

                                <input
                                    type="text"
                                    name="{{ $field }}"
                                    value="{{ old($field, $sendificoAddress->{$field}) }}"
                                    required
                                    class="rounded-md border px-2 py-1.5 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                                >
                            </label>
                        @endforeach

                        <label class="col-span-2 grid gap-1 text-xs text-gray-600 dark:text-gray-300">
                            @lang('sendifico::app.admin.panel.address.street')

                            <textarea
                                name="address"
                                rows="2"
                                required
                                class="rounded-md border px-2 py-1.5 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                            >{{ old('address', $sendificoAddress->address) }}</textarea>
                        </label>

                        @foreach (['city' => 'city', 'state' => 'state', 'postcode' => 'postcode', 'phone' => 'phone'] as $field => $label)
                            <label class="grid gap-1 text-xs text-gray-600 dark:text-gray-300">
                                @lang('sendifico::app.admin.panel.address.'.$label)

                                <input
                                    type="text"
                                    name="{{ $field }}"
                                    value="{{ old($field, $sendificoAddress->{$field}) }}"
                                    @if ($field !== 'postcode') required @endif
                                    class="rounded-md border px-2 py-1.5 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                                >
                            </label>
                        @endforeach

                        <div class="col-span-2">
                            <button type="submit" class="secondary-button">
                                @lang('sendifico::app.admin.panel.address.save')
                            </button>
                        </div>
                    </form>
                </details>
            </div>
        @endif

        {{-- Parcel: measurements and quoting. Re-quoting is always allowed
             before purchase; a destination change re-quotes on its own. --}}
        @if ($sendificoRow?->shipment_id && $sendificoEditable)
            <div class="mt-4 border-t pt-3 dark:border-gray-800">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    @lang('sendifico::app.admin.panel.section-parcel')
                </p>

                <form
                    method="POST"
                    action="{{ route('admin.sendifico.parcel', $order->id) }}"
                    class="mt-2 flex flex-wrap items-end gap-2"
                >
                    @csrf

                    @foreach (['weight' => $sendificoRow->parcel['weight'] ?? '1', 'length' => $sendificoRow->parcel['length'] ?? '1', 'width' => $sendificoRow->parcel['width'] ?? '1', 'height' => $sendificoRow->parcel['height'] ?? '1'] as $field => $value)
                        <label class="grid gap-1 text-xs text-gray-600 dark:text-gray-300">
                            @lang('sendifico::app.admin.panel.parcel.'.$field)

                            <input
                                type="number"
                                step="0.01"
                                min="1"
                                name="{{ $field }}"
                                value="{{ $value }}"
                                required
                                class="w-20 rounded-md border px-2 py-1.5 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                            >
                        </label>
                    @endforeach

                    <button type="submit" class="secondary-button">
                        {{ $sendificoRow->rates ? trans('sendifico::app.admin.panel.requote') : trans('sendifico::app.admin.panel.quote') }}
                    </button>
                </form>
            </div>

            @if ($sendificoRow->rates)
                <div class="mt-4 border-t pt-3 dark:border-gray-800">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        @lang('sendifico::app.admin.panel.section-rates')
                    </p>

                    <form
                        method="POST"
                        action="{{ route('admin.sendifico.purchase', $order->id) }}"
                        class="mt-2"
                    >
                        @csrf

                        <div class="grid gap-1">
                            @foreach ($sendificoRow->rates as $rate)
                                <label class="flex items-center gap-2 text-sm {{ empty($rate['available']) ? 'text-gray-400' : 'text-gray-700 dark:text-gray-300' }}">
                                    <input
                                        type="radio"
                                        name="rate_id"
                                        value="{{ $rate['rateId'] ?? '' }}"
                                        @disabled(empty($rate['available']))
                                        @checked($loop->first && ! empty($rate['available']))
                                    >

                                    {{ $rate['carrierToken'] ?? '' }} — ${{ number_format((float) ($rate['priceTotal'] ?? 0), 2) }}

                                    @if (isset($rate['estimateDays']))
                                        · {{ trans_choice('sendifico::app.admin.panel.estimate-days', (int) $rate['estimateDays'], ['days' => $rate['estimateDays']]) }}
                                    @endif

                                    @if (! empty($rate['unavailableReason']))
                                        · {{ $rate['unavailableReason'] }}
                                    @endif
                                </label>
                            @endforeach
                        </div>

                        <button type="submit" class="primary-button mt-2">
                            @lang('sendifico::app.admin.panel.purchase')
                        </button>
                    </form>
                </div>
            @endif
        @endif

        {{-- Post-purchase actions --}}
        @if ($sendificoRow?->shipment_id)
            @if ($sendificoRow->is_paid && ! $sendificoRow->tracking_number && ! $sendificoRow->is_terminal)
                <form
                    method="POST"
                    action="{{ route('admin.sendifico.tracking', $order->id) }}"
                    class="mt-3"
                >
                    @csrf

                    <button type="submit" class="primary-button">
                        @lang('sendifico::app.admin.panel.generate-tracking')
                    </button>
                </form>

                <p class="mt-1 text-xs text-gray-500">
                    @lang('sendifico::app.admin.panel.tracking-hint')
                </p>
            @endif

            <div class="mt-3 flex flex-wrap items-center gap-2">
                @if ($sendificoRow->tracking_number)
                    <a
                        href="{{ route('admin.sendifico.label', $order->id) }}"
                        target="_blank"
                        class="secondary-button"
                    >
                        @lang('sendifico::app.admin.panel.label')
                    </a>
                @endif

                <form
                    method="POST"
                    action="{{ route('admin.sendifico.refresh', $order->id) }}"
                >
                    @csrf

                    <button type="submit" class="secondary-button">
                        @lang('sendifico::app.admin.panel.refresh')
                    </button>
                </form>
            </div>

            @if (! $sendificoRow->is_terminal)
                <form
                    method="POST"
                    action="{{ route('admin.sendifico.annul', $order->id) }}"
                    class="mt-3 flex flex-wrap items-center gap-2 border-t pt-3 dark:border-gray-800"
                >
                    @csrf

                    <select
                        name="reason"
                        required
                        class="rounded-md border px-2 py-1.5 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                    >
                        @foreach (\Tapstar\Sendifico\Http\Controllers\Admin\ShipmentController::ANNUL_REASONS as $reason)
                            <option value="{{ $reason }}">
                                @lang('sendifico::app.admin.panel.annul-reason.'.$reason)
                            </option>
                        @endforeach
                    </select>

                    <input
                        type="text"
                        name="details"
                        placeholder="@lang('sendifico::app.admin.panel.annul-details')"
                        class="rounded-md border px-2 py-1.5 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                    >

                    <button
                        type="submit"
                        class="secondary-button"
                        onclick="return confirm('@lang('sendifico::app.admin.panel.annul-confirm')')"
                    >
                        @lang('sendifico::app.admin.panel.annul')
                    </button>
                </form>
            @endif
        @endif
    </div>
@endif

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-sendifico-territory-select-template"
    >
        <div class="relative w-[300px] max-w-full">
            <input
                type="text"
                v-model="search"
                required
                autocomplete="off"
                :placeholder="placeholder"
                class="w-full rounded-md border px-2 py-1.5 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                @focus="open = true; $event.target.select()"
                @input="open = true; selectedId = ''"
                @keydown.down.prevent="highlighted = Math.min(highlighted + 1, filtered.length - 1)"
                @keydown.up.prevent="highlighted = Math.max(highlighted - 1, 0)"
                @keydown.enter.prevent="filtered[highlighted] && select(filtered[highlighted])"
                @keydown.esc="open = false"
                @blur="open = false"
            />

            <input
                type="hidden"
                name="territory_base_id"
                :value="selectedId"
            />

            <ul
                v-if="open && filtered.length"
                class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md border bg-white text-sm shadow-lg dark:border-gray-800 dark:bg-gray-900"
            >
                <li
                    v-for="(territory, index) in filtered"
                    :key="territory.id"
                    :class="['cursor-pointer px-2 py-1.5 dark:text-gray-300', index === highlighted ? 'bg-blue-100 dark:bg-gray-800' : '']"
                    @mousedown.prevent="select(territory)"
                    @mouseover="highlighted = index"
                >
                    @{{ territory.text }}
                </li>
            </ul>
        </div>
    </script>

    <script type="module">
        app.component('v-sendifico-territory-select', {
            template: '#v-sendifico-territory-select-template',

            props: ['territories', 'placeholder', 'initialId', 'initialText'],

            data() {
                return {
                    search: this.initialText || '',
                    selectedId: this.initialId || '',
                    open: false,
                    highlighted: 0,
                };
            },

            computed: {
                filtered() {
                    const normalize = (value) => value
                        .normalize('NFD')
                        .replace(/[\u0300-\u036f]/g, '')
                        .toLowerCase();

                    const terms = normalize(this.search).split(/\s+/).filter(Boolean);

                    return this.territories
                        .filter((territory) => {
                            const text = normalize(territory.text);

                            return terms.every((term) => text.includes(term));
                        })
                        .slice(0, 50);
                },
            },

            watch: {
                filtered() {
                    this.highlighted = 0;
                },
            },

            methods: {
                select(territory) {
                    this.selectedId = territory.id;

                    this.search = territory.text;

                    this.open = false;
                },
            },
        });
    </script>
@endPushOnce
