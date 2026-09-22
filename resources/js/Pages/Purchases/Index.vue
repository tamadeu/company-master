<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    BadgeCheck,
    Ban,
    CheckCircle2,
    Clock3,
    PackageCheck,
    ShoppingCart,
    RefreshCw,
    TriangleAlert,
    Truck,
    X,
} from '@lucide/vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

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

interface Offer {
    productId: number;
    productName: string;
    sku: string;
    costCents: number;
    minimumQuantity: number;
}

interface Supplier {
    id: number;
    name: string;
    profile: string;
    leadTimeDays: number;
    paymentTermDays: number;
    reliabilityPercent: number;
    offers: Offer[];
}

interface OrderItem {
    productName: string;
    quantity: number;
    unitCostCents: number;
    totalCents: number;
}

interface Order {
    id: number;
    supplierName: string;
    status: string;
    automatic: boolean;
    buyerName: string | null;
    orderedDate: string;
    expectedDeliveryDate: string;
    receivedDate: string | null;
    totalCents: number;
    paymentStatus: 'paid' | 'payable' | 'cancelled';
    dueDate: string | null;
    canReceive: boolean;
    canCancel: boolean;
    cancellationFeeCents: number;
    items: OrderItem[];
}

interface InventoryCapacity {
    capacityUnits: number;
    stockUnits: number;
    incomingUnits: number;
    usedUnits: number;
    availableUnits: number;
}

interface AutomaticPurchasing {
    reorderPointDays: number;
    targetStockDays: number;
    totalCapacityUnits: number;
    buyers: Array<{ id: number; name: string; role: string; capacityUnits: number }>;
}

const props = defineProps<{
    game: GameSummary;
    company: CompanySummary;
    cashBalanceCents: number;
    automaticPurchasing: AutomaticPurchasing;
    inventoryCapacity: InventoryCapacity;
    suppliers: Supplier[];
    orders: Order[];
}>();

const selectedSupplierId = ref(props.suppliers[0]?.id ?? null);
const quantities = reactive<Record<number, number>>({});
const localError = ref('');
const receivingOrderId = ref<number | null>(null);
const cancellingOrder = ref<Order | null>(null);
const cancellationError = ref('');
const cancellationProcessing = ref(false);
const form = useForm<{
    supplier_id: number | null;
    items: Array<{ product_id: number; quantity: number }>;
}>({
    supplier_id: selectedSupplierId.value,
    items: [],
});

const selectedSupplier = computed(() => props.suppliers.find((supplier) => supplier.id === selectedSupplierId.value) ?? null);
const orderTotalCents = computed(() => selectedSupplier.value?.offers.reduce(
    (total, offer) => total + (offer.costCents * (quantities[offer.productId] ?? 0)),
    0,
) ?? 0);
const orderUnits = computed(() => selectedSupplier.value?.offers.reduce(
    (total, offer) => total + (quantities[offer.productId] ?? 0),
    0,
) ?? 0);
const exceedsCapacity = computed(() => orderUnits.value > props.inventoryCapacity.availableUnits);

const moneyFormatter = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const formatMoney = (cents: number) => moneyFormatter.format(cents / 100);
const formatDate = (date: string | null) => date
    ? new Intl.DateTimeFormat('pt-BR').format(new Date(`${date}T00:00:00`))
    : '—';
const deliveryTimeLabel = (days: number) => days === 0 ? 'Imediata' : `${days} dia(s)`;
const orderStatusLabel = (status: string) => status === 'received'
    ? 'Recebido'
    : status === 'cancelled' ? 'Cancelado' : 'Aguardando entrega';
const orderStatusTone = (status: string) => status === 'received'
    ? 'bg-[#dff7f1] text-[#087c68]'
    : status === 'cancelled' ? 'bg-[#edf1f4] text-[#64798d]' : 'bg-[#fff3dd] text-[#a56500]';
const paymentLabel = (order: Order) => {
    if (order.paymentStatus === 'cancelled') return `Multa ${formatMoney(order.cancellationFeeCents)}`;
    if (order.paymentStatus === 'paid') return 'Pago';

    return `Vence ${formatDate(order.dueDate)}`;
};

const selectSupplier = (supplierId: number) => {
    selectedSupplierId.value = supplierId;
    localError.value = '';
    form.clearErrors();
};

const submitOrder = () => {
    if (!selectedSupplier.value) {
        return;
    }

    const items = selectedSupplier.value.offers
        .filter((offer) => (quantities[offer.productId] ?? 0) > 0)
        .map((offer) => ({ product_id: offer.productId, quantity: quantities[offer.productId] }));

    if (items.length === 0) {
        localError.value = 'Informe a quantidade de ao menos um produto.';
        return;
    }

    localError.value = '';
    form.supplier_id = selectedSupplier.value.id;
    form.items = items;
    form.post(route('games.purchase-orders.store', props.game.id), {
        preserveScroll: true,
        onSuccess: () => {
            Object.keys(quantities).forEach((key) => delete quantities[Number(key)]);
            form.reset('items');
        },
    });
};

const receiveOrder = (order: Order) => {
    receivingOrderId.value = order.id;
    router.post(route('games.purchase-orders.receive', [props.game.id, order.id]), {}, {
        preserveScroll: true,
        onFinish: () => {
            receivingOrderId.value = null;
        },
    });
};

const openCancellation = (order: Order) => {
    cancellingOrder.value = order;
    cancellationError.value = '';
};

const closeCancellation = () => {
    if (!cancellationProcessing.value) {
        cancellingOrder.value = null;
        cancellationError.value = '';
    }
};

const cancelOrder = () => {
    if (!cancellingOrder.value) {
        return;
    }

    cancellationProcessing.value = true;
    router.patch(route('games.purchase-orders.cancel', [props.game.id, cancellingOrder.value.id]), {}, {
        preserveScroll: true,
        onSuccess: () => closeCancellation(),
        onError: (errors) => {
            cancellationError.value = Object.values(errors)[0] ?? 'Não foi possível cancelar o pedido.';
        },
        onFinish: () => {
            cancellationProcessing.value = false;
            if (!cancellationError.value) {
                cancellingOrder.value = null;
            }
        },
    });
};
</script>

<template>
    <Head title="Compras" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-[#102039] lg:text-4xl">Compras</h1>
                <p class="mt-1 text-sm text-[#657a90]">Compare fornecedores, monte pedidos e acompanhe entregas.</p>
            </div>

            <section class="mb-4 flex flex-col gap-4 border-l-4 border-[#1769aa] bg-[#eef6fb] px-5 py-4 sm:flex-row sm:items-center sm:justify-between" aria-label="Reposição automática"><div class="flex items-start gap-3"><RefreshCw :size="21" class="mt-0.5 shrink-0 text-[#1769aa]" /><div><h2 class="text-sm font-bold text-[#19324d]">Reposição automática</h2><p class="mt-1 text-xs leading-5 text-[#61758a]">Dispara com até {{ automaticPurchasing.reorderPointDays }} dias de demanda em estoque e busca cobertura de {{ automaticPurchasing.targetStockDays }} dias.</p></div></div><div class="shrink-0 text-left sm:text-right"><div class="text-lg font-bold text-[#173f67]">{{ automaticPurchasing.totalCapacityUnits }} un./dia</div><div class="text-xs text-[#718599]">{{ automaticPurchasing.buyers.length }} comprador(es) ativo(s)</div></div></section>

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Fornecedores disponíveis">
                <button
                    v-for="supplier in suppliers"
                    :key="supplier.id"
                    type="button"
                    class="rounded-md border bg-white p-4 text-left shadow-sm transition"
                    :class="selectedSupplierId === supplier.id ? 'border-[#18a995] ring-2 ring-[#18a995]/15' : 'border-[#dfe7ee] hover:border-[#a9bac7]'"
                    @click="selectSupplier(supplier.id)"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-sm font-bold text-[#19324d]">{{ supplier.name }}</div>
                            <div class="mt-1 text-xs text-[#718599]">{{ supplier.profile }}</div>
                        </div>
                        <BadgeCheck :size="20" :class="selectedSupplierId === supplier.id ? 'text-[#159580]' : 'text-[#9aabb9]'" />
                    </div>
                    <div class="mt-4 grid grid-cols-3 gap-2 border-t border-[#edf1f4] pt-3 text-center">
                        <div><div class="text-sm font-bold text-[#263e56]">{{ deliveryTimeLabel(supplier.leadTimeDays) }}</div><div class="text-[10px] uppercase text-[#8595a4]">Entrega</div></div>
                        <div><div class="text-sm font-bold text-[#263e56]">{{ supplier.paymentTermDays === 0 ? 'À vista' : `${supplier.paymentTermDays}d` }}</div><div class="text-[10px] uppercase text-[#8595a4]">Pagamento</div></div>
                        <div><div class="text-sm font-bold text-[#263e56]">{{ supplier.reliabilityPercent }}%</div><div class="text-[10px] uppercase text-[#8595a4]">Confiança</div></div>
                    </div>
                </button>
            </section>

            <section v-if="selectedSupplier" class="mt-4 grid gap-4 xl:grid-cols-[1.5fr_0.7fr]">
                <div class="overflow-hidden rounded-md border border-[#dfe7ee] bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-[#e6edf2] px-5 py-4">
                        <div class="flex items-center gap-2 text-sm font-bold text-[#19324d]"><ShoppingCart :size="19" /> Montar pedido</div>
                        <span class="text-xs text-[#75899c]">{{ selectedSupplier.name }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[650px] text-left">
                            <thead class="bg-[#f8fafc] text-[11px] uppercase text-[#74879a]"><tr><th class="px-5 py-3">Produto</th><th class="px-4 py-3">Custo unitário</th><th class="px-4 py-3">Quantidade</th><th class="px-5 py-3 text-right">Subtotal</th></tr></thead>
                            <tbody class="divide-y divide-[#edf1f4]">
                                <tr v-for="offer in selectedSupplier.offers" :key="offer.productId">
                                    <td class="px-5 py-3.5"><div class="text-sm font-semibold text-[#263e56]">{{ offer.productName }}</div><div class="text-[11px] text-[#8a99a8]">{{ offer.sku }}</div></td>
                                    <td class="px-4 py-3.5 text-sm font-medium text-[#263e56]">{{ formatMoney(offer.costCents) }}</td>
                                    <td class="px-4 py-3.5"><input v-model.number="quantities[offer.productId]" type="number" :min="offer.minimumQuantity" max="100000" placeholder="0" class="h-9 w-24 rounded-md border-[#ccd8e1] text-sm focus:border-[#18a995] focus:ring-[#18a995]" /></td>
                                    <td class="px-5 py-3.5 text-right text-sm font-bold text-[#263e56]">{{ formatMoney(offer.costCents * (quantities[offer.productId] ?? 0)) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <aside class="h-fit rounded-md border border-[#dfe7ee] bg-white p-5 shadow-sm">
                    <div class="text-sm font-bold text-[#19324d]">Resumo do pedido</div>
                    <div class="mt-5 space-y-3 text-sm">
                        <div class="flex justify-between text-[#64798d]"><span>Caixa disponível</span><strong class="text-[#263e56]">{{ formatMoney(cashBalanceCents) }}</strong></div>
                        <div class="flex justify-between text-[#64798d]"><span>Entrega prevista</span><strong class="text-[#263e56]">{{ deliveryTimeLabel(selectedSupplier.leadTimeDays) }}</strong></div>
                        <div class="flex justify-between text-[#64798d]"><span>Pagamento</span><strong class="text-[#263e56]">{{ selectedSupplier.paymentTermDays === 0 ? 'À vista' : `${selectedSupplier.paymentTermDays} dias` }}</strong></div>
                        <div class="flex justify-between text-[#64798d]"><span>Vagas disponíveis</span><strong :class="exceedsCapacity ? 'text-[#d65737]' : 'text-[#263e56]'">{{ inventoryCapacity.availableUnits }}</strong></div>
                        <div class="flex justify-between text-[#64798d]"><span>Unidades do pedido</span><strong :class="exceedsCapacity ? 'text-[#d65737]' : 'text-[#263e56]'">{{ orderUnits }}</strong></div>
                    </div>
                    <div class="mt-5 flex items-end justify-between border-t border-[#e6edf2] pt-5"><span class="text-sm text-[#64798d]">Total</span><strong class="text-2xl text-[#102039]">{{ formatMoney(orderTotalCents) }}</strong></div>
                    <p v-if="exceedsCapacity" class="mt-3 text-xs text-red-600">Reduza o pedido ou contrate alguém de Logística para ampliar o estoque.</p>
                    <p v-if="localError || form.errors.items || form.errors.supplier_id" class="mt-3 text-xs text-red-600">{{ localError || form.errors.items || form.errors.supplier_id }}</p>
                    <button type="button" :disabled="form.processing || orderTotalCents === 0 || exceedsCapacity" class="mt-5 inline-flex h-11 w-full items-center justify-center gap-2 rounded-md bg-[#ef654f] px-4 text-sm font-bold text-white disabled:opacity-50" @click="submitOrder"><ShoppingCart :size="17" /> {{ form.processing ? 'Criando...' : 'Criar pedido' }}</button>
                </aside>
            </section>

            <section class="mt-4 overflow-hidden rounded-md border border-[#dfe7ee] bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-[#e6edf2] px-5 py-4"><div class="flex items-center gap-2 text-sm font-bold text-[#19324d]"><Truck :size="19" /> Pedidos</div><span class="text-xs text-[#75899c]">{{ orders.length }} registrado(s)</span></div>
                <div v-if="orders.length === 0" class="grid min-h-44 place-items-center px-6 py-10 text-center"><div><PackageCheck :size="30" class="mx-auto text-[#9babb8]" /><div class="mt-3 text-sm font-bold text-[#354d64]">Nenhum pedido criado</div><p class="mt-1 text-xs text-[#7a8c9d]">Escolha um fornecedor e informe as quantidades acima.</p></div></div>
                <div v-else class="divide-y divide-[#edf1f4]">
                    <article v-for="order in orders" :key="order.id" class="p-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2"><span class="text-sm font-bold text-[#19324d]">Pedido #{{ order.id }}</span><span class="rounded px-2 py-1 text-[11px] font-bold" :class="orderStatusTone(order.status)">{{ orderStatusLabel(order.status) }}</span><span v-if="order.automatic" class="rounded bg-[#e4f0fb] px-2 py-1 text-[11px] font-bold text-[#1769aa]">Automático</span></div>
                                <div class="mt-1 text-xs text-[#718599]">{{ order.supplierName }} · {{ order.items.length }} produto(s)<span v-if="order.buyerName"> · Comprador: {{ order.buyerName }}</span></div>
                            </div>
                            <div class="grid grid-cols-2 gap-x-8 gap-y-3 text-xs sm:grid-cols-4">
                                <div><span class="block text-[#8a99a8]">Total</span><strong class="text-[#263e56]">{{ formatMoney(order.totalCents) }}</strong></div>
                                <div><span class="block text-[#8a99a8]">Entrega</span><strong class="text-[#263e56]">{{ formatDate(order.expectedDeliveryDate) }}</strong></div>
                                <div><span class="block text-[#8a99a8]">Pagamento</span><strong class="text-[#263e56]">{{ paymentLabel(order) }}</strong></div>
                                <div class="col-span-2 flex items-center justify-end gap-2 sm:col-span-1">
                                    <button v-if="order.canReceive" type="button" :disabled="receivingOrderId === order.id" class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-[#159b87] px-3 font-bold text-white" @click="receiveOrder(order)"><PackageCheck :size="15" /> Receber</button>
                                    <span v-else-if="order.status === 'ordered'" class="flex items-center gap-1.5 text-[#718599]"><Clock3 :size="14" /> Em trânsito</span>
                                    <span v-else-if="order.status === 'received'" class="flex items-center gap-1.5 text-[#16836f]"><CheckCircle2 :size="14" /> No estoque</span>
                                    <span v-else class="flex items-center gap-1.5 text-[#718599]"><Ban :size="14" /> Cancelado</span>
                                    <button v-if="order.canCancel" type="button" class="inline-flex h-9 items-center justify-center gap-1.5 rounded-md border border-[#efb5ad] px-3 font-bold text-[#c44032] hover:bg-[#fff5f3]" @click="openCancellation(order)"><Ban :size="14" /> Cancelar</button>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </div>

        <div v-if="cancellingOrder" class="fixed inset-0 z-[80] grid place-items-center bg-[#071729]/60 p-4" @click.self="closeCancellation">
            <div class="w-full max-w-md rounded-md bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-[#e2e9ee] px-6 py-5">
                    <div class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-md bg-[#fff0ed] text-[#c44032]"><TriangleAlert :size="20" /></span><div><h2 class="text-lg font-bold text-[#102039]">Cancelar pedido #{{ cancellingOrder.id }}?</h2><p class="mt-0.5 text-xs text-[#718599]">Esta ação não poderá ser desfeita.</p></div></div>
                    <button type="button" class="grid size-8 place-items-center rounded-md text-[#718599] hover:bg-[#eef3f6]" aria-label="Fechar" @click="closeCancellation"><X :size="18" /></button>
                </div>
                <div class="p-6">
                    <p class="text-sm leading-6 text-[#526a80]">As unidades em trânsito deixarão de ocupar vagas no estoque. Uma multa de <strong class="text-[#c44032]">10% do pedido</strong> será paga imediatamente.</p>
                    <div class="mt-4 flex items-center justify-between rounded-md bg-[#fff5f3] px-4 py-3"><span class="text-sm text-[#765f5d]">Multa de cancelamento</span><strong class="text-lg text-[#c44032]">{{ formatMoney(cancellingOrder.cancellationFeeCents) }}</strong></div>
                    <p v-if="cancellationError" class="mt-3 text-sm text-red-600">{{ cancellationError }}</p>
                    <div class="mt-6 flex justify-end gap-2"><button type="button" :disabled="cancellationProcessing" class="h-10 rounded-md border border-[#cfdbe4] px-4 text-sm font-bold text-[#496177] disabled:opacity-50" @click="closeCancellation">Manter pedido</button><button type="button" :disabled="cancellationProcessing" class="inline-flex h-10 items-center gap-2 rounded-md bg-[#d94f40] px-4 text-sm font-bold text-white hover:bg-[#c44032] disabled:opacity-50" @click="cancelOrder"><Ban :size="16" /> {{ cancellationProcessing ? 'Cancelando...' : 'Confirmar cancelamento' }}</button></div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>