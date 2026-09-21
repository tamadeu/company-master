<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    ArrowRight,
    CalendarCheck,
    BookOpen,
    Boxes,
    Building2,
    Check,
    CircleDollarSign,
    MapPin,
    Flame,
    PackageOpen,
    Play,
    Plus,
    Store,
    Target,
    TriangleAlert,
    TrendingUp,
    Truck,
    Trophy,
    WalletCards,
    X,
} from '@lucide/vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface GameListItem {
    id: number;
    name: string;
    companyName: string;
    status: string;
    currentDate: string;
}

interface GameSummary {
    id: number;
    name: string;
    status: string;
    currentDate: string;
    dayNumber: number;
    victoryDays: number;
}

interface CompanySummary {
    id: number;
    name: string;
    officeLocationName: string;
    difficultyName: string;
}

interface OfficeLocation {
    key: string;
    name: string;
    description: string;
    rent_cents: number;
    demand_factor_basis_points: number;
}

interface Difficulty {
    key: string;
    name: string;
    description: string;
    initial_capital_cents: number;
}

interface Metrics {
    cashBalanceCents: number;
    revenueCents: number;
    netProfitCents: number;
    inventoryValueCents: number;
    payablesNextSevenDaysCents: number;
    equityCents: number;
    victoryTargetCents: number;
}

interface Product {
    id: number;
    sku: string;
    name: string;
    salePriceCents: number;
    baseDailyDemand: number;
    stockQuantity: number;
}

interface Supplier {
    id: number;
    name: string;
    profile: string;
    leadTimeDays: number;
    paymentTermDays: number;
    reliabilityPercent: number;
    lowestOfferCents: number;
}

interface Mission {
    stockPurchased: boolean;
    firstDayCompleted: boolean;
}

interface DaySummary {
    processed_date: string;
    next_date: string;
    sales_revenue_cents: number;
    units_sold: number;
    cogs_cents: number;
    gross_profit_cents: number;
    expenses_cents: number;
    cash_change_cents: number;
    cash_balance_cents: number;
    inventory_value_cents: number;
    stockout_product_ids: number[];
    received_purchase_order_ids: number[];
    event: {
        type: string;
        title: string;
        description: string;
        starts_on: string;
        ends_on: string;
    } | null;
    game_status: string;
    equity_cents: number;
    commercial_capacity_units: number;
    unmet_demand_units: number;
    new_customers: number;
    customer_purchases: number;
}

interface DailyHistoryItem {
    date: string;
    revenueCents: number;
    cashChangeCents: number;
    cashBalanceCents: number | null;
}

interface ActiveEvent {
    type: string;
    title: string;
    description: string;
    payload: Record<string, number | string | boolean>;
    startsOn: string;
    endsOn: string;
}

interface UpcomingDelivery {
    id: number;
    supplierName: string;
    expectedDeliveryDate: string;
    units: number;
    totalCents: number;
}

interface ProductSales {
    productId: number;
    productName: string;
    revenueCents: number;
    unitsSold: number;
}

const props = defineProps<{
    games: GameListItem[];
    game: GameSummary | null;
    company: CompanySummary | null;
    metrics: Metrics | null;
    products: Product[];
    suppliers: Supplier[];
    mission: Mission | null;
    tutorial: { completed: boolean };
    activeEvent: ActiveEvent | null;
    dailyHistory: DailyHistoryItem[];
    upcomingDeliveries: UpcomingDelivery[];
    salesByProduct: ProductSales[];
    manualAdvanceEnabled: boolean;
    officeLocations: OfficeLocation[];
    difficulties: Difficulty[];
    flash?: {
        success?: string | null;
        daySummary?: DaySummary | null;
    };
}>();

const createGameOpen = ref(props.game === null);
const tutorialOpen = ref(Boolean(props.game && !props.tutorial.completed));
const advanceConfirmationOpen = ref(false);
const summaryOpen = ref(Boolean(props.flash?.daySummary));
const form = useForm({
    company_name: '',
    office_location: props.officeLocations.find((location) => location.key === 'downtown')?.key ?? props.officeLocations[0]?.key ?? '',
    difficulty: props.difficulties.find((difficulty) => difficulty.key === 'normal')?.key ?? props.difficulties[0]?.key ?? '',
});
const advanceForm = useForm({
    game_date: props.game?.currentDate ?? '',
});

const moneyFormatter = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
});

const formatMoney = (cents: number) => moneyFormatter.format(cents / 100);
const demandLabel = (basisPoints: number) => basisPoints === 10_000
    ? 'Demanda média'
    : `${basisPoints > 10_000 ? '+' : ''}${((basisPoints - 10_000) / 100).toFixed(0)}% de demanda`;
const formatDate = (date: string) => new Intl.DateTimeFormat('pt-BR').format(new Date(`${date}T00:00:00`));
const objectiveProgress = computed(() => {
    if (!props.metrics || props.metrics.victoryTargetCents === 0) {
        return 0;
    }

    return Math.max(0, Math.min(100, Math.round((props.metrics.equityCents * 100) / props.metrics.victoryTargetCents)));
});
const missionProgress = computed(() => {
    if (!props.mission) {
        return 0;
    }

    return [props.mission.stockPurchased, props.mission.firstDayCompleted].filter(Boolean).length;
});

const cashFlowSeries = computed(() => props.dailyHistory.map((item) => ({
    date: item.date,
    income: item.revenueCents,
    expense: Math.max(0, item.revenueCents - item.cashChangeCents),
    balance: item.cashBalanceCents ?? 0,
})));
const hoveredChartIndex = ref<number | null>(null);
const chartFlowMax = computed(() => Math.max(1, ...cashFlowSeries.value.flatMap((item) => [item.income, item.expense])));
const chartBalanceMin = computed(() => Math.min(...cashFlowSeries.value.map((item) => item.balance)));
const chartBalanceMax = computed(() => Math.max(1, ...cashFlowSeries.value.map((item) => item.balance)));
const chartX = (index: number) => {
    const denominator = Math.max(1, cashFlowSeries.value.length - 1);

    return 28 + (index * 704) / denominator;
};
const chartY = (value: number, key: 'income' | 'expense' | 'balance') => {
    if (key === 'balance') {
        const range = Math.max(1, chartBalanceMax.value - chartBalanceMin.value);

        return 190 - ((value - chartBalanceMin.value) * 160) / range;
    }

    return 190 - (value * 160) / chartFlowMax.value;
};
const chartPoints = (key: 'income' | 'expense' | 'balance') => cashFlowSeries.value
    .map((item, index) => `${chartX(index).toFixed(1)},${chartY(item[key], key).toFixed(1)}`)
    .join(' ');
const hoveredChartPoint = computed(() => hoveredChartIndex.value === null
    ? null
    : cashFlowSeries.value[hoveredChartIndex.value] ?? null);
const updateChartHover = (event: PointerEvent) => {
    const bounds = (event.currentTarget as SVGElement).getBoundingClientRect();
    const relativeX = Math.max(0, Math.min(bounds.width, event.clientX - bounds.left));
    const index = Math.round((relativeX / bounds.width) * Math.max(0, cashFlowSeries.value.length - 1));

    hoveredChartIndex.value = index;
};
const chartDateLabels = computed(() => {
    if (props.dailyHistory.length <= 3) {
        return props.dailyHistory;
    }

    const middleIndex = Math.floor((props.dailyHistory.length - 1) / 2);

    return [props.dailyHistory[0], props.dailyHistory[middleIndex], props.dailyHistory.at(-1)]
        .filter((item): item is DailyHistoryItem => item !== undefined);
});
const maxProductRevenue = computed(() => Math.max(1, ...props.salesByProduct.map((item) => item.revenueCents)));
const deliveryLabel = (date: string) => {
    if (!props.game) {
        return '';
    }

    const difference = Math.round((new Date(`${date}T00:00:00`).getTime() - new Date(`${props.game.currentDate}T00:00:00`).getTime()) / 86_400_000);

    if (difference < 0) return 'Atrasada';
    if (difference === 0) return 'Hoje';
    if (difference === 1) return 'Amanhã';

    return `Em ${difference} dias`;
};

const metricCards = computed(() => {
    if (!props.metrics) {
        return [];
    }

    return [
        { label: 'Caixa disponível', value: formatMoney(props.metrics.cashBalanceCents), detail: 'Capital para suas decisões', icon: WalletCards, tone: 'bg-[#dff7f1] text-[#008b76]' },
        { label: 'Faturamento', value: formatMoney(props.metrics.revenueCents), detail: props.dailyHistory.length ? 'Acumulado da partida' : 'Nenhuma venda processada', icon: TrendingUp, tone: 'bg-[#e4f0fb] text-[#1769aa]' },
        { label: 'Lucro líquido', value: formatMoney(props.metrics.netProfitCents), detail: props.dailyHistory.length ? 'Receita menos CMV e despesas' : 'Aguardando operações', icon: CircleDollarSign, tone: 'bg-[#e1f7ef] text-[#098a68]' },
        { label: 'Valor em estoque', value: formatMoney(props.metrics.inventoryValueCents), detail: props.metrics.inventoryValueCents ? 'Avaliado pelo custo médio' : 'Estoque inicial zerado', icon: Boxes, tone: 'bg-[#fff0e9] text-[#e6653f]' },
    ];
});

const advanceError = computed(() => Object.values(advanceForm.errors)[0] ?? '');

watch(() => props.game?.currentDate, (currentDate) => {
    advanceForm.game_date = currentDate ?? '';
});

watch(() => props.flash?.daySummary, (summary) => {
    if (summary) {
        advanceConfirmationOpen.value = false;
        summaryOpen.value = true;
    }
});

const submitGame = () => {
    form.post(route('games.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createGameOpen.value = false;
            form.reset();
        },
    });
};

const submitAdvance = () => {
    if (!props.game) {
        return;
    }

    advanceForm.post(route('games.advance-day', props.game.id), {
        preserveScroll: true,
    });
};

const completeTutorial = () => {
    if (!props.game) {
        return;
    }

    router.post(route('games.tutorial.complete', props.game.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            tutorialOpen.value = false;
        },
    });
};
</script>

<template>
    <Head title="Visão geral" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-[#102039] lg:text-4xl">Visão geral</h1>
                    <p class="mt-1 text-sm text-[#657a90]">Acompanhe a fundação da empresa e prepare suas primeiras decisões.<span v-if="company"> Escritório: {{ company.officeLocationName }} · Dificuldade: {{ company.difficultyName }}.</span></p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button v-if="game && manualAdvanceEnabled" type="button" class="inline-flex h-11 items-center justify-center gap-2 rounded-md border border-[#cfdbe4] bg-white px-4 text-sm font-bold text-[#31506d]" @click="createGameOpen = true"><Plus :size="18" /> Nova empresa</button>
                    <button v-if="game && manualAdvanceEnabled" type="button" :disabled="advanceForm.processing || game.status !== 'active'" class="inline-flex h-11 items-center justify-center gap-2 rounded-md bg-[#ef654f] px-5 text-sm font-bold text-white shadow-sm transition hover:bg-[#d95340] disabled:opacity-50" @click="advanceConfirmationOpen = true"><Play :size="18" fill="currentColor" /> {{ advanceForm.processing ? 'Processando...' : 'Avançar dia' }}</button>
                    <button v-else-if="!game" type="button" class="inline-flex h-11 items-center justify-center gap-2 rounded-md bg-[#ef654f] px-5 text-sm font-bold text-white shadow-sm" @click="createGameOpen = true"><Plus :size="18" /> Criar empresa</button>
                </div>
            </div>

            <template v-if="game && company && metrics && mission">
                <section v-if="game.status !== 'active'" class="mb-4 flex flex-col gap-4 rounded-md border p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between" :class="game.status === 'won' ? 'border-[#9edbcf] bg-[#e7f8f3]' : 'border-[#f0b8b0] bg-[#fff0ed]'">
                    <div class="flex items-start gap-3"><span class="grid size-11 shrink-0 place-items-center rounded-md" :class="game.status === 'won' ? 'bg-[#c9eee5] text-[#087c68]' : 'bg-[#fbd9d4] text-[#c44032]' "><Trophy v-if="game.status === 'won'" :size="22" /><TriangleAlert v-else :size="22" /></span><div><h2 class="font-bold text-[#19324d]">{{ game.status === 'won' ? 'Partida vencida' : 'Empresa em falência' }}</h2><p class="mt-1 text-sm text-[#61758a]">{{ game.status === 'won' ? 'Você completou o prazo e alcançou o patrimônio necessário.' : 'Uma obrigação vencida não pôde ser paga com o caixa disponível.' }}</p></div></div>
                    <span class="text-sm font-bold" :class="game.status === 'won' ? 'text-[#087c68]' : 'text-[#c44032]'">Patrimônio: {{ formatMoney(metrics.equityCents) }}</span>
                </section>

                <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Indicadores principais">
                    <article v-for="card in metricCards" :key="card.label" class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm">
                        <div class="flex items-start gap-3">
                            <span class="grid size-11 shrink-0 place-items-center rounded-md" :class="card.tone">
                                <component :is="card.icon" :size="22" />
                            </span>
                            <div class="min-w-0">
                                <div class="text-xs font-medium text-[#526980]">{{ card.label }}</div>
                                <div class="mt-0.5 truncate text-2xl font-bold text-[#102039]">{{ card.value }}</div>
                                <div class="mt-1 text-[11px] text-[#8393a3]">{{ card.detail }}</div>
                            </div>
                        </div>
                    </article>
                </section>

                <section class="mt-4 grid gap-4 xl:grid-cols-[1.7fr_1fr]">
                    <div class="min-w-0 rounded-md border border-[#dfe7ee] bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-[#e6edf2] px-5 py-4">
                            <div class="flex items-center gap-2 text-sm font-bold text-[#19324d]">
                                <TrendingUp :size="19" class="text-[#1769aa]" />
                                Fluxo de caixa
                            </div>
                            <div class="flex items-center gap-4 text-[11px] text-[#6f8397]">
                                <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-[#1769aa]"></span>Entradas</span>
                                <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-[#19b6a5]"></span>Saídas</span>
                                <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-[#9bc7e2]"></span>Saldo</span>
                            </div>
                        </div>
                        <div v-if="dailyHistory.length" class="px-4 pb-4 pt-3 sm:px-5">
                            <div class="flex items-center justify-between px-2 text-[10px] font-medium text-[#8393a3]">
                                <span>Fluxos até {{ formatMoney(chartFlowMax) }}</span>
                                <span>Saldo de {{ formatMoney(chartBalanceMin) }} a {{ formatMoney(chartBalanceMax) }}</span>
                            </div>
                            <div class="relative mt-1">
                            <svg class="h-[250px] w-full touch-none" viewBox="0 0 760 220" role="img" aria-label="Entradas, saídas e saldo dos últimos 30 dias" preserveAspectRatio="none" @pointermove="updateChartHover" @pointerleave="hoveredChartIndex = null">
                                <line v-for="position in [30, 70, 110, 150, 190]" :key="position" x1="28" :y1="position" x2="732" :y2="position" stroke="#e8eef3" stroke-width="1" />
                                <polyline :points="chartPoints('balance')" fill="none" stroke="#9bc7e2" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                                <polyline :points="chartPoints('income')" fill="none" stroke="#1769aa" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                                <polyline :points="chartPoints('expense')" fill="none" stroke="#19b6a5" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                                <template v-if="hoveredChartIndex !== null && hoveredChartPoint">
                                    <line :x1="chartX(hoveredChartIndex)" y1="30" :x2="chartX(hoveredChartIndex)" y2="190" stroke="#526980" stroke-width="1" stroke-dasharray="4 4" />
                                    <circle :cx="chartX(hoveredChartIndex)" :cy="chartY(hoveredChartPoint.income, 'income')" r="5" fill="#1769aa" stroke="white" stroke-width="2" />
                                    <circle :cx="chartX(hoveredChartIndex)" :cy="chartY(hoveredChartPoint.expense, 'expense')" r="5" fill="#19b6a5" stroke="white" stroke-width="2" />
                                    <circle :cx="chartX(hoveredChartIndex)" :cy="chartY(hoveredChartPoint.balance, 'balance')" r="5" fill="#9bc7e2" stroke="white" stroke-width="2" />
                                </template>
                            </svg>
                            <div v-if="hoveredChartIndex !== null && hoveredChartPoint" class="pointer-events-none absolute top-2 z-10 min-w-40 rounded-md border border-[#d7e2ea] bg-white px-3 py-2 shadow-lg" :class="hoveredChartIndex === 0 ? '' : hoveredChartIndex === cashFlowSeries.length - 1 ? '-translate-x-full' : '-translate-x-1/2'" :style="{ left: `${(chartX(hoveredChartIndex) / 760) * 100}%` }">
                                <div class="mb-1.5 text-xs font-bold text-[#263e56]">{{ formatDate(hoveredChartPoint.date) }}</div>
                                <div class="space-y-1 text-[11px]">
                                    <div class="flex justify-between gap-4 text-[#1769aa]"><span>Entradas</span><strong>{{ formatMoney(hoveredChartPoint.income) }}</strong></div>
                                    <div class="flex justify-between gap-4 text-[#159a8c]"><span>Saídas</span><strong>{{ formatMoney(hoveredChartPoint.expense) }}</strong></div>
                                    <div class="flex justify-between gap-4 text-[#628aa4]"><span>Saldo</span><strong>{{ formatMoney(hoveredChartPoint.balance) }}</strong></div>
                                </div>
                            </div>
                            </div>
                            <div class="flex justify-between px-2 text-[11px] text-[#718599]">
                                <span v-for="item in chartDateLabels" :key="item.date">{{ formatDate(item.date) }}</span>
                            </div>
                        </div>
                        <div v-else class="grid min-h-[290px] place-items-center px-6 py-10 text-center">
                            <div class="max-w-md">
                                <span class="mx-auto grid size-14 place-items-center rounded-full bg-[#e9f2f8] text-[#27658e]">
                                    <TrendingUp :size="27" />
                                </span>
                                <h2 class="mt-4 text-lg font-bold text-[#19324d]">Seu histórico começa agora</h2>
                                <p class="mt-2 text-sm leading-6 text-[#708398]">Entradas, saídas e saldo aparecerão aqui conforme a empresa operar.</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-md border border-[#dfe7ee] bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 text-sm font-bold text-[#19324d]"><Target :size="19" /> Objetivo da partida</div>
                            <span class="text-xs text-[#75899c]">Dia {{ game.dayNumber }} de {{ game.victoryDays }}</span>
                        </div>
                        <div class="mt-6 flex items-end justify-between gap-4">
                            <div>
                                <div class="text-xs text-[#61758a]">Patrimônio simplificado</div>
                                <div class="mt-1 text-2xl font-bold text-[#102039]">{{ formatMoney(metrics.equityCents) }}</div>
                            </div>
                            <div class="text-right text-xs text-[#61758a]">Meta<br /><strong class="text-[#19324d]">{{ formatMoney(metrics.victoryTargetCents) }}</strong></div>
                        </div>
                        <div class="mt-4 h-2.5 overflow-hidden rounded-full bg-[#e7eef3]">
                            <div class="h-full rounded-full bg-[#19b6a5]" :style="{ width: `${objectiveProgress}%` }"></div>
                        </div>
                        <div class="mt-2 text-right text-xs font-bold text-[#31506d]">{{ objectiveProgress }}%</div>

                        <div class="mt-6 border-t border-[#e6edf2] pt-5">
                            <div class="flex items-center justify-between">
                                <div class="text-xs font-bold uppercase text-[#546a7f]">Primeira missão</div>
                                <span class="text-xs text-[#75899c]">{{ missionProgress }}/2</span>
                            </div>
                            <div class="mt-3 space-y-2.5">
                                <div class="flex items-center gap-3 text-sm" :class="mission.stockPurchased ? 'text-[#16836f]' : 'text-[#435a71]'">
                                    <span class="grid size-6 place-items-center rounded-full" :class="mission.stockPurchased ? 'bg-[#dff7f1]' : 'bg-[#edf2f5]'">
                                        <Check v-if="mission.stockPurchased" :size="14" />
                                        <PackageOpen v-else :size="14" />
                                    </span>
                                    Comprar o primeiro estoque
                                </div>
                                <div class="flex items-center gap-3 text-sm" :class="mission.firstDayCompleted ? 'text-[#16836f]' : 'text-[#435a71]'">
                                    <span class="grid size-6 place-items-center rounded-full" :class="mission.firstDayCompleted ? 'bg-[#dff7f1]' : 'bg-[#edf2f5]'">
                                        <Check v-if="mission.firstDayCompleted" :size="14" />
                                        <ArrowRight v-else :size="14" />
                                    </span>
                                    Concluir o primeiro dia
                                </div>
                            </div>
                        </div>

                        <div v-if="activeEvent" class="mt-5 flex gap-3 rounded-md border border-[#f3c9a7] bg-[#fff7ee] p-4">
                            <span class="grid size-9 shrink-0 place-items-center rounded-md bg-[#ffe4ce] text-[#d65c2f]"><Flame :size="19" /></span>
                            <div class="min-w-0">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="text-[11px] font-bold uppercase text-[#c45027]">Evento ativo</div>
                                    <span class="shrink-0 text-[10px] text-[#7a8997]">Até {{ formatDate(activeEvent.endsOn) }}</span>
                                </div>
                                <div class="mt-0.5 text-sm font-bold text-[#19324d]">{{ activeEvent.title }}</div>
                                <p class="mt-1 text-xs leading-5 text-[#6b7f93]">{{ activeEvent.description }}</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="mt-4 grid gap-4 lg:grid-cols-2">
                    <div class="min-w-0 rounded-md border border-[#dfe7ee] bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-[#e6edf2] px-5 py-4">
                            <div class="flex items-center gap-2 text-sm font-bold text-[#19324d]"><Truck :size="19" class="text-[#1769aa]" /> Próximas entregas</div>
                            <Link :href="route('games.purchases.index', game.id)" class="inline-flex items-center gap-1 text-xs font-semibold text-[#1769aa] hover:text-[#0f517f]">Ver compras <ArrowRight :size="14" /></Link>
                        </div>
                        <div v-if="upcomingDeliveries.length" class="divide-y divide-[#edf1f4]">
                            <div v-for="delivery in upcomingDeliveries" :key="delivery.id" class="flex items-center gap-3 px-5 py-3.5">
                                <span class="grid size-10 shrink-0 place-items-center rounded-md bg-[#e9f2f8] text-[#27658e]"><Truck :size="19" /></span>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-bold text-[#263e56]">{{ delivery.supplierName }}</div>
                                    <div class="mt-0.5 text-xs text-[#75899c]">{{ delivery.units }} itens · {{ formatMoney(delivery.totalCents) }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-[11px] text-[#75899c]">{{ formatDate(delivery.expectedDeliveryDate) }}</div>
                                    <span class="mt-1 inline-block rounded bg-[#e4f0fb] px-2 py-0.5 text-[10px] font-bold text-[#1769aa]">{{ deliveryLabel(delivery.expectedDeliveryDate) }}</span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="grid min-h-48 place-items-center px-6 py-8 text-center">
                            <div><Truck :size="28" class="mx-auto text-[#9aabb9]" /><p class="mt-3 text-sm font-semibold text-[#526a80]">Nenhuma entrega programada</p><p class="mt-1 text-xs text-[#8393a3]">Pedidos em trânsito aparecerão aqui.</p></div>
                        </div>
                    </div>

                    <div class="min-w-0 rounded-md border border-[#dfe7ee] bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-[#e6edf2] px-5 py-4">
                            <div class="flex items-center gap-2 text-sm font-bold text-[#19324d]"><Boxes :size="19" class="text-[#1769aa]" /> Vendas por produto</div>
                            <Link :href="route('games.products.index', game.id)" class="inline-flex items-center gap-1 text-xs font-semibold text-[#1769aa] hover:text-[#0f517f]">Ver vendas <ArrowRight :size="14" /></Link>
                        </div>
                        <div v-if="salesByProduct.length" class="space-y-4 px-5 py-5">
                            <div v-for="productSale in salesByProduct.slice(0, 6)" :key="productSale.productId" class="grid grid-cols-[minmax(7rem,0.8fr)_minmax(8rem,1.3fr)_auto] items-center gap-3">
                                <div class="min-w-0">
                                    <div class="truncate text-xs font-semibold text-[#263e56]">{{ productSale.productName }}</div>
                                    <div class="text-[10px] text-[#8a99a8]">{{ productSale.unitsSold }} un.</div>
                                </div>
                                <div class="h-2.5 overflow-hidden rounded-sm bg-[#e8eef3]"><div class="h-full rounded-sm bg-[#19b6a5]" :style="{ width: `${Math.max(3, Math.round((productSale.revenueCents * 100) / maxProductRevenue))}%` }"></div></div>
                                <div class="min-w-20 text-right text-xs font-bold text-[#31506d]">{{ formatMoney(productSale.revenueCents) }}</div>
                            </div>
                        </div>
                        <div v-else class="grid min-h-48 place-items-center px-6 py-8 text-center">
                            <div><Boxes :size="28" class="mx-auto text-[#9aabb9]" /><p class="mt-3 text-sm font-semibold text-[#526a80]">Nenhuma venda registrada</p><p class="mt-1 text-xs text-[#8393a3]">O desempenho dos produtos aparecerá aqui.</p></div>
                        </div>
                    </div>
                </section>

                <section v-if="games.length > 1" class="mt-4 rounded-md border border-[#dfe7ee] bg-white p-5 shadow-sm">
                    <div class="mb-3 text-sm font-bold text-[#19324d]">Outras partidas</div>
                    <div class="flex flex-wrap gap-2">
                        <Link v-for="item in games.filter((item) => item.id !== game?.id)" :key="item.id" :href="route('games.show', item.id)" class="inline-flex items-center gap-2 rounded-md border border-[#d9e3ea] px-3 py-2 text-sm font-semibold text-[#31506d] hover:bg-[#f4f7fa]">
                            <Building2 :size="16" /> {{ item.companyName }}
                        </Link>
                    </div>
                </section>
            </template>

            <section v-else class="grid min-h-[calc(100vh-13rem)] place-items-center">
                <div class="w-full max-w-2xl rounded-md border border-[#dfe7ee] bg-white p-7 shadow-sm sm:p-10">
                    <span class="grid size-14 place-items-center rounded-md bg-[#dff7f1] text-[#008b76]"><Store :size="28" /></span>
                    <h2 class="mt-5 text-2xl font-bold text-[#102039]">Crie sua primeira empresa</h2>
                    <p class="mt-2 max-w-xl text-sm leading-6 text-[#657a90]">Você começará em 1º de janeiro de 2026 com capital definido pela dificuldade, cinco produtos e três fornecedores para comparar.</p>
                    <button type="button" class="mt-6 inline-flex h-11 items-center gap-2 rounded-md bg-[#ef654f] px-5 text-sm font-bold text-white" @click="createGameOpen = true"><Plus :size="18" /> Criar empresa</button>
                </div>
            </section>
        </div>

        <div v-if="createGameOpen" class="fixed inset-0 z-[70] grid place-items-center overflow-y-auto bg-[#071729]/60 p-4" @click.self="game ? (createGameOpen = false) : null">
            <div class="my-auto w-full max-w-3xl rounded-md bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-[#e2e9ee] px-6 py-5">
                    <div><h2 class="text-xl font-bold text-[#102039]">Configure sua empresa</h2><p class="mt-1 text-sm text-[#6b7f93]">Escolha o nome e a localização inicial do escritório.</p></div>
                    <button v-if="game" type="button" class="grid size-9 place-items-center rounded-md text-[#687d91] hover:bg-[#eef3f6]" aria-label="Fechar" @click="createGameOpen = false"><X :size="20" /></button>
                </div>
                <form class="space-y-6 p-6" @submit.prevent="submitGame">
                    <div><label for="company-name" class="text-sm font-semibold text-[#29415a]">Nome da empresa</label><input id="company-name" v-model="form.company_name" type="text" required maxlength="80" autofocus placeholder="Ex.: Mercado Aurora" class="mt-2 block w-full rounded-md border-[#cdd9e2] text-sm focus:border-[#19a895] focus:ring-[#19a895]" /><p v-if="form.errors.company_name" class="mt-1 text-xs text-red-600">{{ form.errors.company_name }}</p></div>
                    <fieldset><legend class="text-sm font-semibold text-[#29415a]">Local do escritório</legend><p class="mt-1 text-xs text-[#718599]">Aluguel menor reduz custos; regiões valorizadas aumentam a demanda.</p><div class="mt-3 grid gap-3 md:grid-cols-3"><label v-for="location in officeLocations" :key="location.key" class="relative cursor-pointer rounded-md border p-4 transition" :class="form.office_location === location.key ? 'border-[#18a995] bg-[#f0fbf8] ring-2 ring-[#18a995]/15' : 'border-[#d7e2ea] bg-white hover:border-[#a9bac7]'"><input v-model="form.office_location" type="radio" name="office_location" :value="location.key" class="sr-only" /><span class="flex items-start justify-between gap-2"><span class="grid size-9 place-items-center rounded-md" :class="form.office_location === location.key ? 'bg-[#d8f5ed] text-[#087c68]' : 'bg-[#eef3f6] text-[#526a80]'"><MapPin :size="18" /></span><span v-if="form.office_location === location.key" class="grid size-5 place-items-center rounded-full bg-[#18a995] text-white"><Check :size="13" /></span></span><strong class="mt-3 block text-sm text-[#19324d]">{{ location.name }}</strong><span class="mt-1 block min-h-12 text-xs leading-5 text-[#718599]">{{ location.description }}</span><span class="mt-3 block border-t border-[#dfe7ee] pt-3 text-xs text-[#526a80]"><strong class="block text-sm text-[#19324d]">{{ formatMoney(location.rent_cents) }}/mês</strong>{{ demandLabel(location.demand_factor_basis_points) }}</span></label></div><p v-if="form.errors.office_location" class="mt-2 text-xs text-red-600">{{ form.errors.office_location }}</p></fieldset>
                    <fieldset><legend class="text-sm font-semibold text-[#29415a]">Nível de dificuldade</legend><p class="mt-1 text-xs text-[#718599]">Define o capital disponível para começar a operação.</p><div class="mt-3 grid gap-3 sm:grid-cols-3"><label v-for="difficulty in difficulties" :key="difficulty.key" class="cursor-pointer rounded-md border p-4 transition" :class="form.difficulty === difficulty.key ? 'border-[#1769aa] bg-[#f1f7fc] ring-2 ring-[#1769aa]/15' : 'border-[#d7e2ea] bg-white hover:border-[#a9bac7]'"><input v-model="form.difficulty" type="radio" name="difficulty" :value="difficulty.key" class="sr-only" /><span class="flex items-center justify-between gap-2"><span class="grid size-9 place-items-center rounded-md" :class="form.difficulty === difficulty.key ? 'bg-[#dfeefa] text-[#1769aa]' : 'bg-[#eef3f6] text-[#526a80]'"><Target :size="18" /></span><span v-if="form.difficulty === difficulty.key" class="grid size-5 place-items-center rounded-full bg-[#1769aa] text-white"><Check :size="13" /></span></span><strong class="mt-3 block text-sm text-[#19324d]">{{ difficulty.name }}</strong><span class="mt-1 block min-h-10 text-xs leading-5 text-[#718599]">{{ difficulty.description }}</span><span class="mt-3 block border-t border-[#dfe7ee] pt-3 text-xs text-[#526a80]">Capital inicial<strong class="block text-base text-[#19324d]">{{ formatMoney(difficulty.initial_capital_cents) }}</strong></span></label></div><p v-if="form.errors.difficulty" class="mt-2 text-xs text-red-600">{{ form.errors.difficulty }}</p></fieldset>
                    <button type="submit" :disabled="form.processing" class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-md bg-[#ef654f] px-5 text-sm font-bold text-white disabled:opacity-60"><Store :size="18" /> {{ form.processing ? 'Criando...' : 'Começar empresa' }}</button>
                </form>
            </div>
        </div>

        <div v-if="manualAdvanceEnabled && advanceConfirmationOpen && game" class="fixed inset-0 z-[70] grid place-items-center bg-[#071729]/60 p-4" @click.self="advanceConfirmationOpen = false">
            <div class="w-full max-w-md rounded-md bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-[#e2e9ee] px-6 py-5"><div><h2 class="text-xl font-bold text-[#102039]">Avançar o dia?</h2><p class="mt-1 text-sm text-[#6b7f93]">As decisões atuais serão processadas para {{ formatDate(game.currentDate) }}.</p></div><button type="button" class="grid size-9 place-items-center rounded-md text-[#687d91] hover:bg-[#eef3f6]" aria-label="Fechar" @click="advanceConfirmationOpen = false"><X :size="20" /></button></div>
                <div class="p-6"><div class="rounded-md bg-[#f2f7fa] p-4 text-sm leading-6 text-[#50677d]">O sistema receberá entregas vencidas, pagará obrigações, calculará demanda e registrará vendas. A operação é transacional.</div><p v-if="advanceError" class="mt-3 text-sm text-red-600">{{ advanceError }}</p><div class="mt-5 flex justify-end gap-2"><button type="button" class="h-10 rounded-md border border-[#cfdbe4] px-4 text-sm font-bold text-[#496177]" @click="advanceConfirmationOpen = false">Cancelar</button><button type="button" :disabled="advanceForm.processing" class="inline-flex h-10 items-center gap-2 rounded-md bg-[#ef654f] px-4 text-sm font-bold text-white disabled:opacity-50" @click="submitAdvance"><Play :size="16" fill="currentColor" /> {{ advanceForm.processing ? 'Processando...' : 'Confirmar avanço' }}</button></div></div>
            </div>
        </div>

        <div v-if="summaryOpen && flash?.daySummary" class="fixed inset-0 z-[80] grid place-items-center overflow-y-auto bg-[#071729]/60 p-4" @click.self="summaryOpen = false">
            <div class="w-full max-w-2xl rounded-md bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-[#e2e9ee] px-6 py-5"><div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-md bg-[#dff7f1] text-[#087c68]"><CalendarCheck :size="22" /></span><div><h2 class="text-xl font-bold text-[#102039]">Resumo de {{ formatDate(flash.daySummary.processed_date) }}</h2><p class="mt-0.5 text-sm text-[#6b7f93]">Próxima data: {{ formatDate(flash.daySummary.next_date) }}</p></div></div><button type="button" class="grid size-9 place-items-center rounded-md text-[#687d91] hover:bg-[#eef3f6]" aria-label="Fechar resumo" @click="summaryOpen = false"><X :size="20" /></button></div>
                <div class="p-6"><div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><div class="rounded-md bg-[#f4f8fa] p-4"><div class="text-xs text-[#718599]">Faturamento</div><div class="mt-1 text-xl font-bold text-[#19324d]">{{ formatMoney(flash.daySummary.sales_revenue_cents) }}</div></div><div class="rounded-md bg-[#f4f8fa] p-4"><div class="text-xs text-[#718599]">Unidades vendidas</div><div class="mt-1 text-xl font-bold text-[#19324d]">{{ flash.daySummary.units_sold }} / {{ flash.daySummary.commercial_capacity_units }}</div><div class="text-[10px] text-[#8393a3]">vendidas / capacidade</div></div><div class="rounded-md bg-[#f4f8fa] p-4"><div class="text-xs text-[#718599]">Lucro bruto</div><div class="mt-1 text-xl font-bold text-[#19324d]">{{ formatMoney(flash.daySummary.gross_profit_cents) }}</div></div><div class="rounded-md bg-[#f4f8fa] p-4"><div class="text-xs text-[#718599]">Novos clientes</div><div class="mt-1 text-xl font-bold text-[#19324d]">{{ flash.daySummary.new_customers }}</div><div class="text-[10px] text-[#8393a3]">{{ flash.daySummary.customer_purchases }} compra(s)</div></div></div><div class="mt-5 grid gap-3 sm:grid-cols-4"><div class="rounded-md border border-[#dfe7ee] p-4 text-sm text-[#5f7488]"><strong class="block text-[#263e56]">{{ formatMoney(flash.daySummary.expenses_cents) }}</strong>Obrigações pagas</div><div class="rounded-md border border-[#dfe7ee] p-4 text-sm text-[#5f7488]"><strong class="block text-[#263e56]">{{ flash.daySummary.received_purchase_order_ids.length }}</strong>Entregas recebidas</div><div class="rounded-md border border-[#dfe7ee] p-4 text-sm text-[#5f7488]"><strong class="block text-[#263e56]">{{ flash.daySummary.stockout_product_ids.length }}</strong>Produtos com ruptura</div><div class="rounded-md border border-[#dfe7ee] p-4 text-sm text-[#5f7488]"><strong class="block text-[#263e56]">{{ flash.daySummary.unmet_demand_units }}</strong>Demanda não atendida</div></div><div v-if="flash.daySummary.event" class="mt-5 flex items-start gap-3 rounded-md border border-[#f3c9a7] bg-[#fff7ee] p-4"><Flame :size="20" class="mt-0.5 shrink-0 text-[#d65c2f]" /><div><div class="text-xs font-bold uppercase text-[#c45027]">Evento do dia</div><div class="mt-0.5 text-sm font-bold text-[#19324d]">{{ flash.daySummary.event.title }}</div><p class="mt-1 text-xs text-[#6b7f93]">{{ flash.daySummary.event.description }}</p></div></div><div v-if="flash.daySummary.game_status !== 'active'" class="mt-5 rounded-md p-4 text-center" :class="flash.daySummary.game_status === 'won' ? 'bg-[#e7f8f3] text-[#087c68]' : 'bg-[#fff0ed] text-[#c44032]' "><strong class="block">{{ flash.daySummary.game_status === 'won' ? 'Vitória alcançada' : 'Partida encerrada por falência' }}</strong><span class="text-xs">Patrimônio final: {{ formatMoney(flash.daySummary.equity_cents) }}</span></div><button type="button" class="mt-6 h-11 w-full rounded-md bg-[#173f67] text-sm font-bold text-white" @click="summaryOpen = false">Continuar</button></div>
            </div>
        </div>

        <div v-if="tutorialOpen && game" class="fixed inset-0 z-[90] grid place-items-center overflow-y-auto bg-[#071729]/65 p-4">
            <div class="w-full max-w-xl rounded-md bg-white shadow-2xl">
                <div class="border-b border-[#e2e9ee] px-6 py-5"><div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-md bg-[#e4f0fb] text-[#1769aa]"><BookOpen :size="22" /></span><div><h2 class="text-xl font-bold text-[#102039]">Primeiros passos</h2><p class="mt-0.5 text-sm text-[#6b7f93]">Prepare sua empresa antes de avançar o primeiro dia.</p></div></div></div>
                <div class="space-y-4 p-6"><div class="flex gap-3"><span class="grid size-7 shrink-0 place-items-center rounded-full bg-[#173f67] text-xs font-bold text-white">1</span><div><div class="text-sm font-bold text-[#263e56]">Compare fornecedores</div><p class="mt-1 text-xs leading-5 text-[#6b7f93]">Prazo, preço e confiabilidade mudam o risco da compra.</p></div></div><div class="flex gap-3"><span class="grid size-7 shrink-0 place-items-center rounded-full bg-[#173f67] text-xs font-bold text-white">2</span><div><div class="text-sm font-bold text-[#263e56]">Compre estoque e ajuste preços</div><p class="mt-1 text-xs leading-5 text-[#6b7f93]">Sem estoque não há vendas; preços altos reduzem a demanda.</p></div></div><div class="flex gap-3"><span class="grid size-7 shrink-0 place-items-center rounded-full bg-[#173f67] text-xs font-bold text-white">3</span><div><div class="text-sm font-bold text-[#263e56]">{{ manualAdvanceEnabled ? 'Avance um dia e analise' : 'Acompanhe a operação automática' }}</div><p class="mt-1 text-xs leading-5 text-[#6b7f93]">{{ manualAdvanceEnabled ? 'Entregas, contas, vendas e eventos são processados em ordem.' : 'As vendas ocorrem ao longo do dia; à meia-noite o sistema fecha o período e avança a data.' }}</p></div></div><button type="button" class="mt-2 h-11 w-full rounded-md bg-[#ef654f] text-sm font-bold text-white" @click="completeTutorial">Entendi, começar</button></div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>