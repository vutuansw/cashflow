<script setup>
import AdminShell from '../../layouts/AdminShell.vue';
defineProps({ title: String });
</script>

<template>
    <AdminShell :title="title">
        <div class="card p-6 text-center text-muted-foreground">Chức năng này có ở bản cập nhật tới.</div>
    </AdminShell>
</template>
