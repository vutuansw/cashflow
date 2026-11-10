<script setup>
// Nền ảnh bìa mặc định khi chưa tải ảnh: xanh nhạt, hai lớp sóng trắng mờ, cành lá bên phải.
// Đặt trong một khung relative; tự phủ kín khung. Dùng cho trang cá nhân và trang nhóm (plan/08 mục 10c, 10d).
</script>

<template>
    <span class="absolute inset-0 bg-gradient-to-br from-primary/45 via-primary/20 to-primary/5" aria-hidden="true">
        <svg class="absolute inset-0 size-full" viewBox="0 0 600 200" preserveAspectRatio="none">
            <path d="M0 120 C 120 70, 230 150, 360 105 S 540 60, 600 85 L600 200 L0 200 Z" fill="white" fill-opacity="0.22" />
            <path d="M0 150 C 150 110, 260 175, 400 140 S 560 110, 600 125 L600 200 L0 200 Z" fill="white" fill-opacity="0.25" />
        </svg>
        <svg class="absolute right-[6%] top-[10%] h-[70%] text-primary opacity-35" viewBox="0 0 120 120" fill="currentColor">
            <path d="M8 112 C 40 86, 70 58, 104 10" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
            <ellipse cx="96" cy="16" rx="6" ry="15" transform="rotate(35 96 16)" />
            <ellipse cx="78" cy="30" rx="6" ry="15" transform="rotate(-20 78 30)" />
            <ellipse cx="94" cy="44" rx="6" ry="15" transform="rotate(70 94 44)" />
            <ellipse cx="60" cy="50" rx="6" ry="15" transform="rotate(-25 60 50)" />
            <ellipse cx="76" cy="66" rx="6" ry="15" transform="rotate(75 76 66)" />
            <ellipse cx="40" cy="72" rx="6" ry="15" transform="rotate(-30 40 72)" />
            <ellipse cx="56" cy="88" rx="6" ry="15" transform="rotate(80 56 88)" />
        </svg>
    </span>
</template>
