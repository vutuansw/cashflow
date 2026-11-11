<script setup>
// Tên một người: đã có tài khoản trong nhóm thì bấm vào ra trang thành viên, chưa có thì chỉ là chữ. Xem plan/08 mục 6.
defineProps({ name: String, memberHandle: String, code: String });
</script>

<template>
    <RouterLink v-if="memberHandle" :to="`/${code}/members/${memberHandle}`" class="hover:underline" @click.stop>{{ name }}</RouterLink>
    <span v-else>{{ name }}</span>
</template>
