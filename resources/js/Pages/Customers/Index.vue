<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ContactRound, MapPin, ReceiptText, Repeat2, ShoppingBag, Users } from '@lucide/vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

interface GameSummary { id: number; currentDate: string; dayNumber: number; victoryDays: number }
interface CompanySummary { id: number; name: string }
interface CustomerSummary { populationCount: number; customerCount: number; conversionBasisPoints: number; purchaseCount: number; lifetimeValueCents: number; repeatCustomers: number }
interface Customer { id: number; code: string; name: string; gender: string; age: number; city: string; state: string; email: string | null; acquiredOn: string; lastPurchaseOn: string; purchaseCount: number; lifetimeValueCents: number; averageTicketCents: number }
interface Prospect { code: string; name: string; age: number; city: string; state: string }
interface Purchase { id: number; customerId: number; customerName: string; productName: string; quantity: number; revenueCents: number; gameDate: string }

defineProps<{
    game: GameSummary;
    company: CompanySummary;
    summary: CustomerSummary;
    customers: Customer[];
    prospects: Prospect[];
    purchases: Purchase[];
}>();

const activeTab = ref<'customers' | 'prospects'>('customers');
const moneyFormatter = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const formatMoney = (cents: number) => moneyFormatter.format(cents / 100);
const formatDate = (date: string) => new Intl.DateTimeFormat('pt-BR').format(new Date(`${date}T00:00:00`));
</script>

<template>
    <Head title="Clientes" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <div class="mb-6"><h1 class="text-3xl font-bold text-[#102039] lg:text-4xl">Clientes</h1><p class="mt-1 text-sm text-[#657a90]">Acompanhe a conversão da população e o valor gerado por cada cliente.</p></div>

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5" aria-label="Resumo de clientes">
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-md bg-[#e4f0fb] text-[#1769aa]"><Users :size="22" /></span><div><div class="text-xs text-[#64798d]">População</div><div class="text-2xl font-bold text-[#102039]">{{ summary.populationCount }}</div></div></div></article>
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-md bg-[#dff7f1] text-[#008b76]"><ContactRound :size="22" /></span><div><div class="text-xs text-[#64798d]">Clientes</div><div class="text-2xl font-bold text-[#102039]">{{ summary.customerCount }}</div><div class="text-[11px] text-[#8393a3]">{{ (summary.conversionBasisPoints / 100).toFixed(2) }}% convertidos</div></div></div></article>
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-md bg-[#fff3dd] text-[#b56b00]"><ShoppingBag :size="22" /></span><div><div class="text-xs text-[#64798d]">Compras registradas</div><div class="text-2xl font-bold text-[#102039]">{{ summary.purchaseCount }}</div></div></div></article>
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-md bg-[#fff0e9] text-[#e6653f]"><ReceiptText :size="22" /></span><div><div class="text-xs text-[#64798d]">Valor da carteira</div><div class="text-2xl font-bold text-[#102039]">{{ formatMoney(summary.lifetimeValueCents) }}</div></div></div></article>
                <article class="rounded-md border border-[#dfe7ee] bg-white p-4 shadow-sm"><div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-md bg-[#f0edf8] text-[#6650a4]"><Repeat2 :size="22" /></span><div><div class="text-xs text-[#64798d]">Clientes recorrentes</div><div class="text-2xl font-bold text-[#102039]">{{ summary.repeatCustomers }}</div></div></div></article>
            </section>

            <section class="mt-4 overflow-hidden rounded-md border border-[#dfe7ee] bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-[#e6edf2] px-5 py-4 sm:flex-row sm:items-center sm:justify-between"><div class="flex items-center gap-2 text-sm font-bold text-[#19324d]"><ContactRound :size="19" /> Base de relacionamento</div><div class="inline-flex rounded-md bg-[#eef3f6] p-1"><button type="button" class="rounded px-3 py-1.5 text-xs font-bold" :class="activeTab === 'customers' ? 'bg-white text-[#173f67] shadow-sm' : 'text-[#718599]'" @click="activeTab = 'customers'">Clientes ({{ customers.length }})</button><button type="button" class="rounded px-3 py-1.5 text-xs font-bold" :class="activeTab === 'prospects' ? 'bg-white text-[#173f67] shadow-sm' : 'text-[#718599]'" @click="activeTab = 'prospects'">Prospects</button></div></div>
                <div v-if="activeTab === 'customers' && customers.length" class="overflow-x-auto"><table class="w-full min-w-[980px] text-left"><thead class="bg-[#f8fafc] text-[11px] uppercase text-[#74879a]"><tr><th class="px-5 py-3">Cliente</th><th class="px-4 py-3">Localização</th><th class="px-4 py-3">Aquisição</th><th class="px-4 py-3 text-right">Compras</th><th class="px-4 py-3 text-right">Ticket médio</th><th class="px-5 py-3 text-right">Valor vitalício</th></tr></thead><tbody class="divide-y divide-[#edf1f4]"><tr v-for="customer in customers" :key="customer.id"><td class="px-5 py-3.5"><Link :href="route('games.customers.show', [game.id, customer.id])" class="text-sm font-semibold text-[#1769aa] hover:underline">{{ customer.name }}</Link><div class="text-[11px] text-[#8a99a8]">{{ customer.code }} · {{ customer.age }} anos</div></td><td class="px-4 py-3.5 text-sm text-[#61758a]"><span class="inline-flex items-center gap-1"><MapPin :size="13" /> {{ customer.city }}/{{ customer.state }}</span></td><td class="px-4 py-3.5 text-sm text-[#61758a]">{{ formatDate(customer.acquiredOn) }}</td><td class="px-4 py-3.5 text-right text-sm font-semibold text-[#354d64]">{{ customer.purchaseCount }}</td><td class="px-4 py-3.5 text-right text-sm text-[#61758a]">{{ formatMoney(customer.averageTicketCents) }}</td><td class="px-5 py-3.5 text-right text-sm font-bold text-[#087c68]">{{ formatMoney(customer.lifetimeValueCents) }}</td></tr></tbody></table></div>
                <div v-else-if="activeTab === 'customers'" class="grid min-h-56 place-items-center p-8 text-center"><div><ContactRound :size="34" class="mx-auto text-[#9babb8]" /><h2 class="mt-3 text-sm font-bold text-[#354d64]">Nenhum cliente ainda</h2><p class="mt-1 text-xs text-[#7a8c9d]">NPCs da população serão convertidos automaticamente quando realizarem compras.</p></div></div>
                <div v-else class="overflow-x-auto"><table class="w-full min-w-[680px] text-left"><thead class="bg-[#f8fafc] text-[11px] uppercase text-[#74879a]"><tr><th class="px-5 py-3">NPC</th><th class="px-4 py-3">Idade</th><th class="px-5 py-3">Localização</th></tr></thead><tbody class="divide-y divide-[#edf1f4]"><tr v-for="prospect in prospects" :key="prospect.code"><td class="px-5 py-3.5"><div class="text-sm font-semibold text-[#263e56]">{{ prospect.name }}</div><div class="text-[11px] text-[#8a99a8]">{{ prospect.code }}</div></td><td class="px-4 py-3.5 text-sm text-[#61758a]">{{ prospect.age }} anos</td><td class="px-5 py-3.5 text-sm text-[#61758a]">{{ prospect.city }}/{{ prospect.state }}</td></tr></tbody></table></div>
            </section>

            <section class="mt-4 overflow-hidden rounded-md border border-[#dfe7ee] bg-white shadow-sm"><div class="flex items-center justify-between border-b border-[#e6edf2] px-5 py-4"><div class="flex items-center gap-2 text-sm font-bold text-[#19324d]"><ReceiptText :size="19" /> Compras recentes</div><span class="text-xs text-[#75899c]">{{ purchases.length }} registro(s)</span></div><div v-if="purchases.length" class="overflow-x-auto"><table class="w-full min-w-[760px] text-left"><thead class="bg-[#f8fafc] text-[11px] uppercase text-[#74879a]"><tr><th class="px-5 py-3">Data</th><th class="px-4 py-3">Cliente</th><th class="px-4 py-3">Produto</th><th class="px-4 py-3 text-right">Quantidade</th><th class="px-5 py-3 text-right">Valor</th></tr></thead><tbody class="divide-y divide-[#edf1f4]"><tr v-for="purchase in purchases" :key="purchase.id"><td class="px-5 py-3.5 text-sm text-[#61758a]">{{ formatDate(purchase.gameDate) }}</td><td class="px-4 py-3.5"><Link :href="route('games.customers.show', [game.id, purchase.customerId])" class="text-sm font-semibold text-[#1769aa] hover:underline">{{ purchase.customerName }}</Link></td><td class="px-4 py-3.5 text-sm text-[#61758a]">{{ purchase.productName }}</td><td class="px-4 py-3.5 text-right text-sm">{{ purchase.quantity }}</td><td class="px-5 py-3.5 text-right text-sm font-bold text-[#087c68]">{{ formatMoney(purchase.revenueCents) }}</td></tr></tbody></table></div><div v-else class="p-8 text-center text-sm text-[#7a8c9d]">As compras aparecerão após processar vendas com estoque.</div></section>
        </div>
    </AuthenticatedLayout>
</template>