<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Boxes, CircleDollarSign, History, Package, PackageOpen, Save, Warehouse } from '@lucide/vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

interface GameSummary { id: number; currentDate: string; dayNumber: number; victoryDays: number }
interface CompanySummary { id: number; name: string }
interface Balance {
    productId: number;
    productName: string;
    sku: string;
    salePriceCents: number;
    referencePriceCents: number;
    baseDailyDemand: number;
    quantity: number;
    averageCostCents: number;
    totalValueCents: number;
}
interface Movement { id: number; productName: string; type: string; quantity: number; unitCostCents: number; totalCostCents: number; gameDate: string; referenceId: number }

const props = defineProps<{
    game: GameSummary;
    company: CompanySummary;
    summary: { totalUnits: number; totalValueCents: number; productsInStock: number; capacityUnits: number; incomingUnits: number; usedUnits: number; availableUnits: number; logisticsEmployees: number };
    balances: Balance[];
    movements: Movement[];
}>();

const moneyFormatter = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const formatMoney = (cents: number) => moneyFormatter.format(cents / 100);
const formatDate = (date: string) => new Intl.DateTimeFormat('pt-BR').format(new Date(`${date}T00:00:00`));
const priceInputs = reactive<Record<number, string>>(
    Object.fromEntries(props.balances.map((item) => [item.productId, `${Math.floor(item.salePriceCents / 100)},${String(item.salePriceCents % 100).padStart(2, '0')}`])),
);
const errors = reactive<Record<number, string>>({});
const savingProductId = ref<number | null>(null);
const capacityPercentage = computed(() => props.summary.capacityUnits > 0
    ? Math.min(100, Math.round((props.summary.usedUnits * 100) / props.summary.capacityUnits))
    : 0);

const parseCents = (value: string): number | null => {
    const match = value.trim().match(/^(\d+)(?:[,.](\d{1,2}))?$/);
    if (!match) return null;

    return (Number(match[1]) * 100) + Number((match[2] ?? '').padEnd(2, '0'));
};

const savePrice = (product: Balance) => {
    const cents = parseCents(priceInputs[product.productId] ?? '');
    if (!cents) {
        errors[product.productId] = 'Informe um preço válido, como 34,90.';
        return;
    }

    errors[product.productId] = '';
    savingProductId.value = product.productId;
    router.patch(route('games.products.update', [props.game.id, product.productId]), {
        sale_price_cents: cents,
    }, {
        preserveScroll: true,
        onError: (responseErrors) => {
            errors[product.productId] = responseErrors.sale_price_cents ?? 'Não foi possível atualizar o preço.';
        },
        onFinish: () => {
            savingProductId.value = null;
        },
    });
};
</script>

<template>
    <Head title="Estoque e produtos" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <div class="mb-6"><h1 class="text-3xl font-bold text-[#102039] lg:text-4xl">Estoque e produtos</h1><p class="mt-1 text-sm text-[#657a90]">Cadastre a estratégia comercial dos produtos e acompanhe posição e movimentos.</p></div>

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Resumo do estoque">
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-md bg-[#dff7f1] text-[#008b76]"><Boxes :size="22" /></span><div><div class="text-xs text-[#64798d]">Unidades em estoque</div><div class="text-2xl font-bold text-[#102039]">{{ summary.totalUnits }}</div></div></div></article>
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-md bg-[#e4f0fb] text-[#1769aa]"><Package :size="22" /></span><div><div class="text-xs text-[#64798d]">Produtos com saldo</div><div class="text-2xl font-bold text-[#102039]">{{ summary.productsInStock }}</div></div></div></article>
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-md bg-[#fff0e9] text-[#e6653f]"><CircleDollarSign :size="22" /></span><div><div class="text-xs text-[#64798d]">Valor a custo</div><div class="text-2xl font-bold text-[#102039]">{{ formatMoney(summary.totalValueCents) }}</div></div></div></article>
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-md bg-[#f0edf8] text-[#6650a4]"><Warehouse :size="22" /></span><div class="min-w-0 flex-1"><div class="text-xs text-[#64798d]">Capacidade logística</div><div class="text-2xl font-bold text-[#102039]">{{ summary.usedUnits }} / {{ summary.capacityUnits }}</div><div class="mt-1 text-[11px] text-[#8393a3]">{{ summary.incomingUnits }} em trânsito · {{ summary.availableUnits }} vagas</div></div></div><div class="mt-3 h-2 overflow-hidden rounded-full bg-[#e8eef3]"><div class="h-full rounded-full" :class="capacityPercentage >= 90 ? 'bg-[#d65737]' : 'bg-[#6650a4]'" :style="{ width: `${capacityPercentage}%` }"></div></div></article>
            </section>

            <section class="mt-4 overflow-hidden rounded-md border border-[#dfe7ee] bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-[#e6edf2] px-5 py-4"><div class="flex items-center gap-2 text-sm font-bold text-[#19324d]"><PackageOpen :size="19" /> Cadastro e posição por produto</div><span class="text-xs text-[#75899c]">Preço de venda altera a demanda do próximo dia</span></div>
                <div class="overflow-x-auto"><table class="w-full min-w-[1050px] text-left"><thead class="bg-[#f8fafc] text-[11px] uppercase text-[#74879a]"><tr><th class="px-5 py-3">Produto</th><th class="px-4 py-3">Referência</th><th class="px-4 py-3">Preço de venda</th><th class="px-4 py-3 text-right">Demanda base</th><th class="px-4 py-3 text-right">Quantidade</th><th class="px-4 py-3 text-right">Custo médio</th><th class="px-5 py-3 text-right">Valor total</th></tr></thead><tbody class="divide-y divide-[#edf1f4]"><tr v-for="balance in balances" :key="balance.productId"><td class="px-5 py-4"><div class="text-sm font-semibold text-[#263e56]">{{ balance.productName }}</div><div class="text-[11px] text-[#8a99a8]">{{ balance.sku }}</div></td><td class="px-4 py-4 text-sm text-[#61758a]">{{ formatMoney(balance.referencePriceCents) }}</td><td class="px-4 py-4"><div class="flex items-center gap-2"><span class="text-sm text-[#61758a]">R$</span><input v-model="priceInputs[balance.productId]" type="text" inputmode="decimal" class="h-9 w-24 rounded-md border-[#ccd8e1] text-sm focus:border-[#18a995] focus:ring-[#18a995]" @keyup.enter="savePrice(balance)" /><button type="button" :disabled="savingProductId === balance.productId" class="grid size-9 place-items-center rounded-md bg-[#173f67] text-white disabled:opacity-50" :aria-label="`Salvar preço de ${balance.productName}`" @click="savePrice(balance)"><Save :size="15" /></button></div><p v-if="errors[balance.productId]" class="mt-1 text-xs text-red-600">{{ errors[balance.productId] }}</p></td><td class="px-4 py-4 text-right text-sm text-[#61758a]">{{ balance.baseDailyDemand }} un./dia</td><td class="px-4 py-4 text-right"><span class="rounded px-2 py-1 text-xs font-bold" :class="balance.quantity > 0 ? 'bg-[#dff7f1] text-[#087c68]' : 'bg-[#fff0e9] text-[#d65737]'">{{ balance.quantity }} un.</span></td><td class="px-4 py-4 text-right text-sm text-[#435a71]">{{ formatMoney(balance.averageCostCents) }}</td><td class="px-5 py-4 text-right text-sm font-bold text-[#263e56]">{{ formatMoney(balance.totalValueCents) }}</td></tr></tbody></table></div>
            </section>

            <section class="mt-4 overflow-hidden rounded-md border border-[#dfe7ee] bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-[#e6edf2] px-5 py-4"><div class="flex items-center gap-2 text-sm font-bold text-[#19324d]"><History :size="19" /> Movimentações</div><span class="text-xs text-[#75899c]">{{ movements.length }} registro(s)</span></div>
                <div v-if="movements.length === 0" class="grid min-h-44 place-items-center px-6 py-10 text-center"><div><History :size="30" class="mx-auto text-[#9babb8]" /><div class="mt-3 text-sm font-bold text-[#354d64]">Nenhuma movimentação</div><p class="mt-1 text-xs text-[#7a8c9d]">Entradas e saídas aparecerão conforme compras e vendas forem processadas.</p></div></div>
                <div v-else class="overflow-x-auto"><table class="w-full min-w-[720px] text-left"><thead class="bg-[#f8fafc] text-[11px] uppercase text-[#74879a]"><tr><th class="px-5 py-3">Data</th><th class="px-4 py-3">Produto</th><th class="px-4 py-3">Origem</th><th class="px-4 py-3 text-right">Quantidade</th><th class="px-5 py-3 text-right">Custo total</th></tr></thead><tbody class="divide-y divide-[#edf1f4]"><tr v-for="movement in movements" :key="movement.id"><td class="px-5 py-3.5 text-sm text-[#61758a]">{{ formatDate(movement.gameDate) }}</td><td class="px-4 py-3.5 text-sm font-semibold text-[#263e56]">{{ movement.productName }}</td><td class="px-4 py-3.5 text-sm text-[#61758a]">{{ movement.type === 'sale' ? `Venda #${movement.referenceId}` : `Pedido #${movement.referenceId}` }}</td><td class="px-4 py-3.5 text-right text-sm font-bold" :class="movement.quantity > 0 ? 'text-[#16836f]' : 'text-[#d65737]'">{{ movement.quantity > 0 ? '+' : '' }}{{ movement.quantity }}</td><td class="px-5 py-3.5 text-right text-sm font-bold text-[#263e56]">{{ formatMoney(movement.totalCostCents) }}</td></tr></tbody></table></div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>