<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Bell, ChevronRight, CircleDollarSign, PackageCheck, Users } from '@lucide/vue';
import { Head, Link } from '@inertiajs/vue3';

interface SaleMetadata {
    game_id: number;
    game_name: string;
    company_name: string;
    game_date: string;
    revenue_cents: number;
    units_sold: number;
    new_customers: number;
}
interface SaleNotification {
    id: number;
    subject: string;
    body: string;
    actionUrl: string | null;
    metadata: SaleMetadata;
    wasUnread: boolean;
    createdAt: string;
}
interface PageLink { url: string | null; label: string; active: boolean }
interface PaginatedNotifications { data: SaleNotification[]; links: PageLink[]; from: number | null; to: number | null; total: number }

defineProps<{ notifications: PaginatedNotifications }>();

const moneyFormatter = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const formatMoney = (cents: number) => moneyFormatter.format(cents / 100);
const formatGameDate = (value: string) => new Intl.DateTimeFormat('pt-BR').format(new Date(`${value}T00:00:00`));
const formatTimestamp = (value: string) => new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
}).format(new Date(value));
const cleanLabel = (label: string) => ({
    'pagination.previous': 'Anterior',
    'pagination.next': 'Próxima',
}[label] ?? label.replace('&laquo;', '').replace('&raquo;', '').trim());
</script>

<template>
    <Head title="Notificações de vendas" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-[1300px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <header class="flex flex-col gap-3 border-b border-[#d7e2ea] pb-6 sm:flex-row sm:items-end sm:justify-between">
                <div><div class="flex items-center gap-2 text-xs font-bold uppercase text-[#087c68]"><Bell :size="16" /> Atividade em background</div><h1 class="mt-2 text-3xl font-bold text-[#102039]">Notificações de vendas</h1><p class="mt-1 text-sm text-[#657a90]">Vendas processadas enquanto suas partidas continuam operando.</p></div>
                <div class="text-sm font-semibold text-[#526a80]">{{ notifications.total }} processamento(s)</div>
            </header>

            <section class="mt-6 overflow-hidden border border-[#dce5ec] bg-white shadow-sm" aria-label="Histórico de notificações de vendas">
                <div v-if="notifications.data.length === 0" class="grid min-h-80 place-items-center px-8 text-center"><div><Bell :size="38" class="mx-auto text-[#9baab7]" /><h2 class="mt-4 text-base font-bold text-[#354d64]">Nenhuma venda notificada</h2><p class="mt-1 max-w-md text-sm leading-6 text-[#718499]">Quando uma partida processar vendas, o resumo aparecerá aqui mesmo que você esteja desconectado.</p></div></div>

                <div v-else class="divide-y divide-[#e8eef2]">
                    <article v-for="notification in notifications.data" :key="notification.id" class="relative grid gap-5 px-5 py-5 lg:grid-cols-[minmax(240px,1fr)_repeat(3,minmax(120px,0.45fr))_42px] lg:items-center lg:px-6">
                        <span v-if="notification.wasUnread" class="absolute left-0 top-0 h-full w-1 bg-[#18a995]"></span>
                        <div class="min-w-0"><div class="flex items-center gap-2"><span class="grid size-9 shrink-0 place-items-center rounded-md bg-[#dff7f1] text-[#087c68]"><CircleDollarSign :size="19" /></span><div class="min-w-0"><h2 class="truncate text-sm font-bold text-[#19324d]">{{ notification.metadata.company_name }}</h2><p class="truncate text-xs text-[#718499]">{{ notification.metadata.game_name }} · {{ formatGameDate(notification.metadata.game_date) }}</p></div></div><p class="mt-2 text-xs text-[#8a99a7]">Processado em {{ formatTimestamp(notification.createdAt) }}</p></div>
                        <div><div class="text-xs text-[#718499]">Faturamento</div><div class="mt-1 text-lg font-bold text-[#19324d]">{{ formatMoney(notification.metadata.revenue_cents) }}</div></div>
                        <div><div class="flex items-center gap-1 text-xs text-[#718499]"><PackageCheck :size="14" /> Unidades</div><div class="mt-1 text-lg font-bold text-[#19324d]">{{ notification.metadata.units_sold }}</div></div>
                        <div><div class="flex items-center gap-1 text-xs text-[#718499]"><Users :size="14" /> Novos clientes</div><div class="mt-1 text-lg font-bold text-[#19324d]">{{ notification.metadata.new_customers }}</div></div>
                        <Link v-if="notification.actionUrl" :href="notification.actionUrl" class="grid size-10 place-items-center rounded-md border border-[#d7e2ea] text-[#17638d] hover:bg-[#f1f7fa]" title="Ver registros de vendas"><ChevronRight :size="19" /></Link>
                    </article>
                </div>

                <div v-if="notifications.links.length > 3" class="flex flex-col gap-3 border-t border-[#e6edf2] px-5 py-4 text-xs text-[#718499] sm:flex-row sm:items-center sm:justify-between"><span>{{ notifications.from ?? 0 }}–{{ notifications.to ?? 0 }} de {{ notifications.total }}</span><div class="flex flex-wrap gap-1"><Link v-for="link in notifications.links" :key="link.label" :href="link.url ?? '#'" class="grid min-h-8 min-w-8 place-items-center rounded px-2 font-semibold" :class="link.active ? 'bg-[#173f67] text-white' : link.url ? 'border border-[#d7e2ea] text-[#526a80]' : 'text-[#b0bdc8]'">{{ cleanLabel(link.label) }}</Link></div></div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>