<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Boxes, CircleDollarSign, Gauge, PackageOpen, Save, ShoppingBag, TrendingUp } from '@lucide/vue';
import { Head, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

interface GameSummary {
    id: number;
    currentDate: string;
    dayNumber: number;
    victoryDays: number;
}

interface CompanySummary {
    id: number;
    name: string;
}

interface Product {
    id: number;
    sku: string;
    name: string;
    salePriceCents: number;
    referencePriceCents: number;
    baseDailyDemand: number;
    stockQuantity: number;
    unitsSold: number;
    revenueCents: number;
}

interface SalesRules {
    dailyCapacityUnits: number;
    commercialEmployees: number;
    ownerBaseCapacityUnits: number;
    productivityMinBasisPoints: number;
    productivityMaxBasisPoints: number;
    latestUnmetDemandUnits: number;
}

const props = defineProps<{
    game: GameSummary;
    company: CompanySummary;
    products: Product[];
    salesRules: SalesRules;
}>();

const priceInputs = reactive<Record<number, string>>(
    Object.fromEntries(props.products.map((product) => [product.id, `${Math.floor(product.salePriceCents / 100)},${String(product.salePriceCents % 100).padStart(2, '0')}`])),
);
const errors = reactive<Record<number, string>>({});
const savingProductId = ref<number | null>(null);
const moneyFormatter = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const formatMoney = (cents: number) => moneyFormatter.format(cents / 100);

const parseCents = (value: string): number | null => {
    const match = value.trim().match(/^(\d+)(?:[,.](\d{1,2}))?$/);
    if (!match) {
        return null;
    }

    return (Number(match[1]) * 100) + Number((match[2] ?? '').padEnd(2, '0'));
};

const savePrice = (product: Product) => {
    const cents = parseCents(priceInputs[product.id] ?? '');
    if (!cents || cents < 1) {
        errors[product.id] = 'Informe um preço válido, como 34,90.';
        return;
    }

    errors[product.id] = '';
    savingProductId.value = product.id;
    router.patch(route('games.products.update', [props.game.id, product.id]), {
        sale_price_cents: cents,
    }, {
        preserveScroll: true,
        onError: (responseErrors) => {
            errors[product.id] = responseErrors.sale_price_cents ?? 'Não foi possível atualizar o preço.';
        },
        onFinish: () => {
            savingProductId.value = null;
        },
    });
};
</script>

<template>
    <Head title="Vendas e preços" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-[#102039] lg:text-4xl">Vendas e preços</h1>
                <p class="mt-1 text-sm text-[#657a90]">Defina preços e acompanhe o desempenho automático de cada produto.</p>
            </div>

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Resumo comercial">
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-md bg-[#dff7f1] text-[#008b76]"><CircleDollarSign :size="22" /></span><div><div class="text-xs text-[#64798d]">Faturamento acumulado</div><div class="text-2xl font-bold text-[#102039]">{{ formatMoney(products.reduce((total, product) => total + product.revenueCents, 0)) }}</div></div></div></article>
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-md bg-[#e4f0fb] text-[#1769aa]"><ShoppingBag :size="22" /></span><div><div class="text-xs text-[#64798d]">Unidades vendidas</div><div class="text-2xl font-bold text-[#102039]">{{ products.reduce((total, product) => total + product.unitsSold, 0) }}</div></div></div></article>
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-md bg-[#fff0e9] text-[#e6653f]"><Boxes :size="22" /></span><div><div class="text-xs text-[#64798d]">Estoque disponível</div><div class="text-2xl font-bold text-[#102039]">{{ products.reduce((total, product) => total + product.stockQuantity, 0) }}</div></div></div></article>
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-md bg-[#f0edf8] text-[#6650a4]"><Gauge :size="22" /></span><div><div class="text-xs text-[#64798d]">Capacidade de vendas hoje</div><div class="text-2xl font-bold text-[#102039]">{{ salesRules.dailyCapacityUnits }} un.</div><div class="text-[11px] text-[#8393a3]">{{ salesRules.commercialEmployees }} pessoa(s) no Comercial</div></div></div></article>
            </section>

            <section class="mt-4 overflow-hidden rounded-md border border-[#dfe7ee] bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-[#e6edf2] px-5 py-4"><div class="flex items-center gap-2 text-sm font-bold text-[#19324d]"><TrendingUp :size="19" /> Catálogo comercial</div><span class="text-xs text-[#75899c]">Preços alteram a demanda do próximo dia</span></div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[920px] text-left">
                        <thead class="bg-[#f8fafc] text-[11px] uppercase text-[#74879a]"><tr><th class="px-5 py-3">Produto</th><th class="px-4 py-3">Referência</th><th class="px-4 py-3">Preço de venda</th><th class="px-4 py-3 text-right">Estoque</th><th class="px-4 py-3 text-right">Vendido</th><th class="px-5 py-3 text-right">Faturamento</th></tr></thead>
                        <tbody class="divide-y divide-[#edf1f4]">
                            <tr v-for="product in products" :key="product.id">
                                <td class="px-5 py-4"><div class="text-sm font-semibold text-[#263e56]">{{ product.name }}</div><div class="text-[11px] text-[#8a99a8]">{{ product.sku }} · demanda base {{ product.baseDailyDemand }}/dia</div></td>
                                <td class="px-4 py-4 text-sm text-[#61758a]">{{ formatMoney(product.referencePriceCents) }}</td>
                                <td class="px-4 py-4"><div class="flex items-center gap-2"><span class="text-sm text-[#61758a]">R$</span><input v-model="priceInputs[product.id]" type="text" inputmode="decimal" class="h-9 w-24 rounded-md border-[#ccd8e1] text-sm focus:border-[#18a995] focus:ring-[#18a995]" @keyup.enter="savePrice(product)" /><button type="button" :disabled="savingProductId === product.id" class="grid size-9 place-items-center rounded-md bg-[#173f67] text-white disabled:opacity-50" :aria-label="`Salvar preço de ${product.name}`" @click="savePrice(product)"><Save :size="15" /></button></div><p v-if="errors[product.id]" class="mt-1 text-xs text-red-600">{{ errors[product.id] }}</p></td>
                                <td class="px-4 py-4 text-right"><span class="rounded px-2 py-1 text-xs font-bold" :class="product.stockQuantity > 0 ? 'bg-[#dff7f1] text-[#087c68]' : 'bg-[#fff0e9] text-[#d65737]'">{{ product.stockQuantity }} un.</span></td>
                                <td class="px-4 py-4 text-right text-sm font-semibold text-[#354d64]">{{ product.unitsSold }}</td>
                                <td class="px-5 py-4 text-right text-sm font-bold text-[#263e56]">{{ formatMoney(product.revenueCents) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="mt-4 rounded-md border border-[#dfe7ee] bg-white p-6 shadow-sm">
                <div class="flex items-start gap-4"><span class="grid size-11 shrink-0 place-items-center rounded-md bg-[#e9f2f8] text-[#27658e]"><PackageOpen :size="21" /></span><div><h2 class="text-sm font-bold text-[#19324d]">Como as vendas são calculadas</h2><p class="mt-1 text-sm leading-6 text-[#6b7f93]">A demanda varia diariamente entre {{ salesRules.productivityMinBasisPoints / 100 }}% e {{ salesRules.productivityMaxBasisPoints / 100 }}%, reage ao preço e aos eventos. A venda final respeita estoque e capacidade comercial. O gestor cobre {{ salesRules.ownerBaseCapacityUnits }} un./dia; funcionários do Comercial ampliam esse limite conforme o cargo.</p><p v-if="salesRules.latestUnmetDemandUnits" class="mt-2 text-xs font-semibold text-[#c45027]">Último dia: {{ salesRules.latestUnmetDemandUnits }} unidade(s) de demanda não atendida.</p></div></div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>