<script setup lang="ts">
withDefaults(defineProps<{
    variant?: 'full' | 'mark';
}>(), {
    variant: 'full',
});
</script>

<template>
    <img
        :src="variant === 'mark' ? '/images/company-master-mark.png' : '/images/company-master-logo-full.png'"
        :alt="variant === 'mark' ? 'Company Master' : 'Company Master'"
        class="block object-contain"
    />
</template>