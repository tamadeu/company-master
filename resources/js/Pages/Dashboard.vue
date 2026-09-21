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
    Landmark,
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
    manualAdvanceEnabled: boolean;
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
    name: '',
    company_name: '',
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

const historyMaxRevenue = computed(() => Math.max(1, ...props.dailyHistory.map((item) => item.revenueCents)));
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
                    <p class="mt-1 text-sm text-[#657a90]">Acompanhe a fundação da empresa e prepare suas primeiras decisões.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button v-if="game" type="button" class="inline-flex h-11 items-center justify-center gap-2 rounded-md border border-[#cfdbe4] bg-white px-4 text-sm font-bold text-[#31506d]" @click="createGameOpen = true"><Plus :size="18" /> Nova partida</button>
                    <button v-if="game && manualAdvanceEnabled" type="button" :disabled="advanceForm.processing || game.status !== 'active'" class="inline-flex h-11 items-center justify-center gap-2 rounded-md bg-[#ef654f] px-5 text-sm font-bold text-white shadow-sm transition hover:bg-[#d95340] disabled:opacity-50" @click="advanceConfirmationOpen = true"><Play :size="18" fill="currentColor" /> {{ advanceForm.processing ? 'Processando...' : 'Avançar dia' }}</button>
                    <button v-else type="button" class="inline-flex h-11 items-center justify-center gap-2 rounded-md bg-[#ef654f] px-5 text-sm font-bold text-white shadow-sm" @click="createGameOpen = true"><Plus :size="18" /> Nova partida</button>
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

                <section v-if="activeEvent" class="mt-4 flex flex-col gap-4 rounded-md border border-[#f3c9a7] bg-[#fff7ee] p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-3"><span class="grid size-11 shrink-0 place-items-center rounded-md bg-[#ffe4ce] text-[#d65c2f]"><Flame :size="22" /></span><div><div class="text-xs font-bold uppercase text-[#c45027]">Evento ativo</div><h2 class="mt-0.5 font-bold text-[#19324d]">{{ activeEvent.title }}</h2><p class="mt-1 text-sm text-[#6b7f93]">{{ activeEvent.description }}</p><p v-if="activeEvent.payload.product_name" class="mt-1 text-xs font-semibold text-[#c45027]">{{ activeEvent.payload.product_name }}</p></div></div>
                    <div class="text-xs text-[#6b7f93]">Até {{ formatDate(activeEvent.endsOn) }}</div>
                </section>

                <section class="mt-4 grid gap-4 xl:grid-cols-[1.55fr_1fr]">
                    <div class="rounded-md border border-[#dfe7ee] bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-[#e6edf2] px-5 py-4">
                            <div class="flex items-center gap-2 text-sm font-bold text-[#19324d]">
                                <Landmark :size="19" class="text-[#1769aa]" />
                                Fundação financeira
                            </div>
                            <span class="text-xs text-[#75899c]">Últimos {{ dailyHistory.length }} dia(s)</span>
                        </div>
                        <div v-if="dailyHistory.length" class="space-y-3 px-5 py-5">
                            <div v-for="item in dailyHistory.slice(-7)" :key="item.date" class="grid grid-cols-[5rem_1fr_auto] items-center gap-3">
                                <span class="text-xs text-[#6e8296]">{{ formatDate(item.date) }}</span>
                                <div class="h-2 overflow-hidden rounded-full bg-[#e8eef3]"><div class="h-full rounded-full bg-[#19b6a5]" :style="{ width: `${Math.max(2, Math.round((item.revenueCents * 100) / historyMaxRevenue))}%` }"></div></div>
                                <div class="min-w-24 text-right"><div class="text-xs font-bold text-[#263e56]">{{ formatMoney(item.revenueCents) }}</div><div class="text-[10px]" :class="item.cashChangeCents >= 0 ? 'text-[#16836f]' : 'text-[#d65737]'">{{ item.cashChangeCents >= 0 ? '+' : '' }}{{ formatMoney(item.cashChangeCents) }}</div></div>
                            </div>
                        </div>
                        <div v-else class="grid min-h-64 place-items-center px-6 py-10 text-center">
                            <div class="max-w-md">
                                <span class="mx-auto grid size-14 place-items-center rounded-full bg-[#e9f2f8] text-[#27658e]">
                                    <TrendingUp :size="27" />
                                </span>
                                <h2 class="mt-4 text-lg font-bold text-[#19324d]">Seu histórico começa agora</h2>
                                <p class="mt-2 text-sm leading-6 text-[#708398]">O fluxo de caixa será preenchido conforme sua empresa registrar operações e avançar na simulação.</p>
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
                    </div>
                </section>

                <section class="mt-4 grid gap-4 xl:grid-cols-[1.35fr_1fr]">
                    <div class="overflow-hidden rounded-md border border-[#dfe7ee] bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-[#e6edf2] px-5 py-4">
                            <div class="flex items-center gap-2 text-sm font-bold text-[#19324d]"><PackageOpen :size="19" class="text-[#e6653f]" /> Produtos iniciais</div>
                            <span class="text-xs text-[#75899c]">{{ products.length }} cadastrados</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[620px] text-left">
                                <thead class="bg-[#f8fafc] text-[11px] uppercase text-[#74879a]">
                                    <tr><th class="px-5 py-3 font-semibold">Produto</th><th class="px-4 py-3 font-semibold">Preço</th><th class="px-4 py-3 font-semibold">Demanda base</th><th class="px-5 py-3 text-right font-semibold">Estoque</th></tr>
                                </thead>
                                <tbody class="divide-y divide-[#edf1f4]">
                                    <tr v-for="product in products" :key="product.id" class="text-sm">
                                        <td class="px-5 py-3.5"><div class="font-semibold text-[#263e56]">{{ product.name }}</div><div class="text-[11px] text-[#8a99a8]">{{ product.sku }}</div></td>
                                        <td class="px-4 py-3.5 font-medium text-[#263e56]">{{ formatMoney(product.salePriceCents) }}</td>
                                        <td class="px-4 py-3.5 text-[#61758a]">{{ product.baseDailyDemand }} un./dia</td>
                                        <td class="px-5 py-3.5 text-right"><span class="rounded bg-[#fff0e9] px-2 py-1 text-xs font-bold text-[#d65737]">{{ product.stockQuantity }} un.</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="rounded-md border border-[#dfe7ee] bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-[#e6edf2] px-5 py-4">
                            <div class="flex items-center gap-2 text-sm font-bold text-[#19324d]"><Truck :size="19" class="text-[#1769aa]" /> Fornecedores</div>
                            <span class="text-xs text-[#75899c]">{{ suppliers.length }} opções</span>
                        </div>
                        <div class="divide-y divide-[#edf1f4]">
                            <div v-for="supplier in suppliers" :key="supplier.id" class="p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div><div class="text-sm font-bold text-[#263e56]">{{ supplier.name }}</div><div class="mt-0.5 text-xs text-[#75899c]">{{ supplier.profile }} · entrega em {{ supplier.leadTimeDays }} dia(s)</div></div>
                                    <span class="rounded bg-[#e4f0fb] px-2 py-1 text-[11px] font-bold text-[#1769aa]">{{ supplier.reliabilityPercent }}%</span>
                                </div>
                                <div class="mt-3 flex items-center justify-between text-xs text-[#61758a]"><span>Menor oferta: <strong class="text-[#263e56]">{{ formatMoney(supplier.lowestOfferCents) }}</strong></span><span>{{ supplier.paymentTermDays === 0 ? 'À vista' : `${supplier.paymentTermDays} dias` }}</span></div>
                            </div>
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
                    <p class="mt-2 max-w-xl text-sm leading-6 text-[#657a90]">Você começará em 1º de janeiro de 2026 com R$ 100 mil em caixa, cinco produtos e três fornecedores para comparar.</p>
                    <button type="button" class="mt-6 inline-flex h-11 items-center gap-2 rounded-md bg-[#ef654f] px-5 text-sm font-bold text-white" @click="createGameOpen = true"><Plus :size="18" /> Criar partida</button>
                </div>
            </section>
        </div>

        <div v-if="createGameOpen" class="fixed inset-0 z-[70] grid place-items-center bg-[#071729]/60 p-4" @click.self="game ? (createGameOpen = false) : null">
            <div class="w-full max-w-lg rounded-md bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-[#e2e9ee] px-6 py-5">
                    <div><h2 class="text-xl font-bold text-[#102039]">Nova partida</h2><p class="mt-1 text-sm text-[#6b7f93]">Defina os nomes para começar sua empresa.</p></div>
                    <button v-if="game" type="button" class="grid size-9 place-items-center rounded-md text-[#687d91] hover:bg-[#eef3f6]" aria-label="Fechar" @click="createGameOpen = false"><X :size="20" /></button>
                </div>
                <form class="space-y-5 p-6" @submit.prevent="submitGame">
                    <div><label for="game-name" class="text-sm font-semibold text-[#29415a]">Nome da partida</label><input id="game-name" v-model="form.name" type="text" required maxlength="80" placeholder="Ex.: Primeiros passos" class="mt-2 block w-full rounded-md border-[#cdd9e2] text-sm focus:border-[#19a895] focus:ring-[#19a895]" /><p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p></div>
                    <div><label for="company-name" class="text-sm font-semibold text-[#29415a]">Nome da empresa</label><input id="company-name" v-model="form.company_name" type="text" required maxlength="80" placeholder="Ex.: Mercado Aurora" class="mt-2 block w-full rounded-md border-[#cdd9e2] text-sm focus:border-[#19a895] focus:ring-[#19a895]" /><p v-if="form.errors.company_name" class="mt-1 text-xs text-red-600">{{ form.errors.company_name }}</p></div>
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
                <div class="space-y-4 p-6"><div class="flex gap-3"><span class="grid size-7 shrink-0 place-items-center rounded-full bg-[#173f67] text-xs font-bold text-white">1</span><div><div class="text-sm font-bold text-[#263e56]">Compare fornecedores</div><p class="mt-1 text-xs leading-5 text-[#6b7f93]">Prazo, preço e confiabilidade mudam o risco da compra.</p></div></div><div class="flex gap-3"><span class="grid size-7 shrink-0 place-items-center rounded-full bg-[#173f67] text-xs font-bold text-white">2</span><div><div class="text-sm font-bold text-[#263e56]">Compre estoque e ajuste preços</div><p class="mt-1 text-xs leading-5 text-[#6b7f93]">Sem estoque não há vendas; preços altos reduzem a demanda.</p></div></div><div class="flex gap-3"><span class="grid size-7 shrink-0 place-items-center rounded-full bg-[#173f67] text-xs font-bold text-white">3</span><div><div class="text-sm font-bold text-[#263e56]">Avance um dia e analise</div><p class="mt-1 text-xs leading-5 text-[#6b7f93]">Entregas, contas, vendas e eventos são processados em ordem.</p></div></div><button type="button" class="mt-2 h-11 w-full rounded-md bg-[#ef654f] text-sm font-bold text-white" @click="completeTutorial">Entendi, começar</button></div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>