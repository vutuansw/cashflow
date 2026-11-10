<script setup>
// Khung iPhone vẽ bằng CSS cho landing page (plan/14): viền máy, Dynamic Island,
// thanh trạng thái 9:41 và thanh home. Ảnh chụp app 390×844 nằm dưới thanh trạng thái.
defineProps({
    src: { type: String, required: true },
    alt: { type: String, required: true },
    // Bề rộng máy (px) trên màn rộng; dưới 640px tự thu lại theo `mobileWidth`.
    width: { type: Number, default: 290 },
    mobileWidth: { type: Number, default: 250 },
    // Ảnh ở màn đầu tiên thì tải ngay, còn lại tải lười.
    eager: { type: Boolean, default: false },
    // Màu thanh trạng thái, nên trùng màu phần đầu ảnh chụp.
    statusBg: { type: String, default: '#f6f7f6' },
});
</script>

<template>
    <div class="phone" :style="{ '--w': `${width}px`, '--w-sm': `${mobileWidth}px` }">
        <div class="screen">
            <span class="island" aria-hidden="true"></span>
            <div class="statusbar" :style="{ background: statusBg }" aria-hidden="true">
                <span>9:41</span>
                <svg viewBox="0 0 58 13" class="h-[0.7em] w-auto" fill="currentColor">
                    <rect x="0" y="8" width="3" height="5" rx="1" /><rect x="5" y="5.5" width="3" height="7.5" rx="1" /><rect x="10" y="3" width="3" height="10" rx="1" /><rect x="15" y="0" width="3" height="13" rx="1" />
                    <path d="M27 4.5a9 9 0 0 1 12 0l-1.5 1.6a7 7 0 0 0-9 0zM29.5 7.3a5.5 5.5 0 0 1 7 0L33 11z" />
                    <rect x="43" y="1" width="13" height="11" rx="3" fill="none" stroke="currentColor" stroke-width="1.3" /><rect x="45" y="3" width="9" height="7" rx="1.5" />
                </svg>
            </div>
            <img :src="src" :alt="alt" width="780" height="1688" :loading="eager ? 'eager' : 'lazy'" :fetchpriority="eager ? 'high' : 'auto'" decoding="async" class="shot" />
            <span class="homebar" aria-hidden="true"></span>
        </div>
    </div>
</template>

<style scoped>
/* Màu máy và màn hình cố định (ảnh chụp ở chế độ sáng), không theo token giao diện. */
.phone {
    --size: var(--w);
    position: relative;
    flex-shrink: 0;
    width: var(--size);
    aspect-ratio: 390 / 844;
    padding: calc(var(--size) * 0.035);
    border-radius: calc(var(--size) * 0.17);
    background: linear-gradient(145deg, #2b2f33, #0d0f10 55%, #24282b);
    box-shadow: 0 0 0 1.5px #4a4f54 inset, 0 40px 80px -20px rgb(10 60 40 / 0.35), 0 18px 30px -12px rgb(0 0 0 / 0.35);
}
@media (max-width: 639px) {
    .phone { --size: var(--w-sm); }
}
/* Nút âm lượng bên trái và nút nguồn bên phải. */
.phone::before,
.phone::after {
    content: '';
    position: absolute;
    width: 3px;
    border-radius: 2px;
    background: #2b2f33;
}
.phone::before { left: -3px; top: 22%; height: 11%; box-shadow: 0 calc(var(--size) * 0.2) 0 #2b2f33; }
.phone::after { right: -3px; top: 28%; height: 15%; }
.screen {
    position: relative;
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
    border-radius: calc(var(--size) * 0.135);
    background: #fff;
}
.statusbar {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: space-between;
    height: calc(var(--size) * 0.13);
    padding: 0 calc(var(--size) * 0.085) 0 calc(var(--size) * 0.1);
    color: #111;
    font-size: calc(var(--size) * 0.046);
    font-weight: 600;
}
.island {
    position: absolute;
    top: calc(var(--size) * 0.028);
    left: 50%;
    z-index: 1;
    width: 32%;
    height: calc(var(--size) * 0.085);
    transform: translateX(-50%);
    border-radius: 999px;
    background: #000;
}
.shot {
    display: block;
    flex: 1;
    min-height: 0;
    width: 100%;
    object-fit: cover;
    object-position: top;
}
.homebar {
    position: absolute;
    bottom: 7px;
    left: 50%;
    z-index: 1;
    width: 36%;
    height: 4px;
    transform: translateX(-50%);
    border-radius: 4px;
    background: rgb(0 0 0 / 0.75);
}
</style>
