<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { BellRing, ChevronRight, CircleAlert, CircleDollarSign, Inbox, Mail, MailOpen, Megaphone, ShoppingCart, Trash2 } from '@lucide/vue';
import { Head, Link, router } from '@inertiajs/vue3';

interface InboxMessage {
    id: number;
    category: string;
    subject: string;
    body: string;
    actionUrl: string | null;
    readAt: string | null;
    createdAt: string;
    senderName: string;
}
interface PageLink { url: string | null; label: string; active: boolean }
interface PaginatedMessages { data: InboxMessage[]; links: PageLink[]; from: number | null; to: number | null; total: number }

defineProps<{
    messages: PaginatedMessages;
    selectedMessage: InboxMessage | null;
}>();

const formatDate = (value: string) => new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
}).format(new Date(value));

const categoryLabel = (category: string) => ({
    admin: 'Administração',
    alert: 'Alerta',
    event: 'Evento',
    operation: 'Operação',
    outcome: 'Resultado',
    sale: 'Vendas',
    purchase: 'Compras',
}[category] ?? 'Sistema');

const categoryIcon = (category: string) => ({
    admin: Megaphone,
    alert: CircleAlert,
    event: BellRing,
    operation: Inbox,
    outcome: BellRing,
    sale: CircleDollarSign,
    purchase: ShoppingCart,
}[category] ?? Mail);

const removeMessage = (message: InboxMessage) => {
    if (window.confirm(`Remover “${message.subject}” da caixa de entrada?`)) {
        router.delete(route('inbox.destroy', message.id));
    }
};

const cleanLabel = (label: string) => ({
    'pagination.previous': 'Anterior',
    'pagination.next': 'Próxima',
}[label] ?? label.replace('&laquo;', '').replace('&raquo;', '').trim());
</script>

<template>
    <Head title="Caixa de entrada" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <header class="border-b border-[#d7e2ea] pb-6">
                <div class="flex items-center gap-2 text-xs font-bold uppercase text-[#087c68]"><Inbox :size="16" /> Comunicação</div>
                <h1 class="mt-2 text-3xl font-bold text-[#102039]">Caixa de entrada</h1>
                <p class="mt-1 text-sm text-[#657a90]">Alertas operacionais e avisos da administração.</p>
            </header>

            <div class="mt-6 grid min-h-[620px] overflow-hidden border border-[#dbe5ec] bg-white shadow-sm lg:grid-cols-[400px_minmax(0,1fr)]">
                <section class="border-b border-[#dbe5ec] lg:border-b-0 lg:border-r" aria-label="Mensagens recebidas">
                    <div class="flex h-14 items-center justify-between border-b border-[#e7edf2] px-5">
                        <div class="text-sm font-bold text-[#19324d]">Entrada</div>
                        <div class="text-xs text-[#718499]">{{ messages.total }} mensagem(ns)</div>
                    </div>

                    <div v-if="messages.data.length === 0" class="grid min-h-72 place-items-center px-8 text-center">
                        <div><MailOpen :size="34" class="mx-auto text-[#9baab7]" /><h2 class="mt-3 text-sm font-bold text-[#354d64]">Tudo em dia</h2><p class="mt-1 text-xs leading-5 text-[#718499]">Novos alertas e avisos aparecerão aqui.</p></div>
                    </div>

                    <div v-else class="divide-y divide-[#edf1f4]">
                        <Link v-for="message in messages.data" :key="message.id" :href="route('inbox.show', message.id)" class="group grid grid-cols-[38px_minmax(0,1fr)_18px] gap-3 px-4 py-4 transition hover:bg-[#f6fafc]" :class="selectedMessage?.id === message.id ? 'bg-[#edf6f8]' : ''">
                            <span class="grid size-9 place-items-center rounded-md" :class="message.readAt ? 'bg-[#edf1f4] text-[#718499]' : 'bg-[#d9f6ef] text-[#087c68]'"><component :is="categoryIcon(message.category)" :size="18" /></span>
                            <span class="min-w-0"><span class="flex items-center gap-2"><span class="truncate text-sm" :class="message.readAt ? 'font-semibold text-[#405970]' : 'font-bold text-[#102f4c]'">{{ message.subject }}</span><span v-if="!message.readAt" class="size-2 shrink-0 rounded-full bg-[#14a58f]"></span></span><span class="mt-1 block truncate text-xs text-[#718499]">{{ message.senderName }} · {{ formatDate(message.createdAt) }}</span><span class="mt-1 block truncate text-xs text-[#8a99a7]">{{ message.body }}</span></span>
                            <ChevronRight :size="17" class="self-center text-[#a3b1bd] group-hover:text-[#17638d]" />
                        </Link>
                    </div>

                    <div v-if="messages.links.length > 3" class="flex flex-wrap gap-1 border-t border-[#e7edf2] p-4 text-xs"><Link v-for="link in messages.links" :key="link.label" :href="link.url ?? '#'" class="grid min-h-8 min-w-8 place-items-center rounded px-2 font-semibold" :class="link.active ? 'bg-[#173f67] text-white' : link.url ? 'border border-[#d7e2ea] text-[#526a80]' : 'text-[#b0bdc8]'">{{ cleanLabel(link.label) }}</Link></div>
                </section>

                <section class="min-w-0" aria-label="Leitura da mensagem">
                    <div v-if="selectedMessage" class="flex min-h-full flex-col">
                        <div class="border-b border-[#e7edf2] px-5 py-5 sm:px-7">
                            <div class="flex items-start justify-between gap-4"><div><span class="text-xs font-bold uppercase text-[#087c68]">{{ categoryLabel(selectedMessage.category) }}</span><h2 class="mt-2 text-xl font-bold text-[#102039] sm:text-2xl">{{ selectedMessage.subject }}</h2></div><button type="button" title="Excluir mensagem" class="grid size-10 shrink-0 place-items-center rounded-md border border-[#d9e3ea] text-[#b44b42] hover:bg-[#fff3f1]" @click="removeMessage(selectedMessage)"><Trash2 :size="18" /></button></div>
                            <div class="mt-3 text-xs text-[#718499]">De {{ selectedMessage.senderName }} · {{ formatDate(selectedMessage.createdAt) }}</div>
                        </div>
                        <div class="flex-1 px-5 py-7 sm:px-7"><p class="max-w-3xl whitespace-pre-wrap text-sm leading-7 text-[#405970]">{{ selectedMessage.body }}</p><Link v-if="selectedMessage.actionUrl" :href="selectedMessage.actionUrl" class="mt-8 inline-flex h-10 items-center gap-2 rounded-md bg-[#173f67] px-4 text-sm font-bold text-white">Ver detalhes <ChevronRight :size="16" /></Link></div>
                    </div>
                    <div v-else class="grid min-h-[360px] place-items-center px-8 text-center lg:min-h-full"><div><Mail :size="40" class="mx-auto text-[#a4b2be]" /><h2 class="mt-4 text-base font-bold text-[#354d64]">Selecione uma mensagem</h2><p class="mt-1 text-sm text-[#718499]">Escolha um item da caixa de entrada para ler.</p></div></div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>