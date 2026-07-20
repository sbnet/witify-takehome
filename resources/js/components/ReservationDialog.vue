<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import ReservationController from '@/actions/App/Http/Controllers/ReservationController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { OrderLine, Warehouse } from '@/types';

const props = defineProps<{
    line: OrderLine | null;
    warehouses: Warehouse[];
}>();

const open = defineModel<boolean>('open', { required: true });

const form = useForm({
    order_line_id: 0,
    warehouse_id: null as number | null,
    quantity: 1,
});

watch(open, (isOpen) => {
    if (isOpen) {
        form.reset();
        form.clearErrors();
    }
});

const availableInWarehouse = computed(() => {
    if (!props.line || form.warehouse_id === null) {
        return null;
    }

    return props.line.availability[form.warehouse_id] ?? 0;
});

function submit() {
    if (!props.line) {
        return;
    }

    const lineId = props.line.id;

    form.transform((data) => ({
        ...data,
        order_line_id: lineId,
    })).post(ReservationController.store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Reserve inventory</DialogTitle>
                <DialogDescription v-if="line">
                    {{ line.product.name }} ({{ line.product.sku }}) &mdash;
                    {{ line.quantity_remaining }} of
                    {{ line.quantity_ordered }} left to reserve
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="reservation-warehouse">Warehouse</Label>
                    <Select v-model="form.warehouse_id">
                        <SelectTrigger
                            id="reservation-warehouse"
                            class="w-full"
                        >
                            <SelectValue placeholder="Select a warehouse" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="warehouse in warehouses"
                                :key="warehouse.id"
                                :value="warehouse.id"
                            >
                                {{ warehouse.name }} ({{ warehouse.code }})
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p
                        v-if="availableInWarehouse !== null"
                        class="text-sm text-muted-foreground"
                    >
                        Available in this warehouse:
                        {{ availableInWarehouse }}
                    </p>
                    <InputError :message="form.errors.warehouse_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="reservation-quantity">Quantity</Label>
                    <Input
                        id="reservation-quantity"
                        v-model.number="form.quantity"
                        type="number"
                        min="1"
                        required
                    />
                    <InputError :message="form.errors.quantity" />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        :disabled="
                            form.processing || form.warehouse_id === null
                        "
                    >
                        {{ form.processing ? 'Reserving...' : 'Reserve' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
