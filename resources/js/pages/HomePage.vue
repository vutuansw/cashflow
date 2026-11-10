<script setup>
// Trang chủ: landing page giới thiệu WePo, xem plan/14 và wireframes/landing.html.
// Hero + 5 section ưu việt (ảnh app thật trong khung iPhone) + CTA. Đã đăng nhập thì
// các nút chính đổi thành "Vào nhóm" (thanh trên cùng là nút viền).
import { ArrowDown, ArrowRight, Check, Heart, MessageCircle, Sparkles, Star } from 'lucide-vue-next';
import BrandMark from '../components/BrandMark.vue';
import LandingSection from '../components/landing/LandingSection.vue';
import PhoneFrame from '../components/landing/PhoneFrame.vue';
import { homePath, session } from '../lib/session.js';

// Nhóm công khai để khách xem thử, cấu hình LANDING_DEMO_GROUP (app.blade.php in ra thẻ meta).
const year = new Date().getFullYear();
const demoGroup = document.querySelector('meta[name="wepo-demo-group"]')?.content || null;

const nav = [
    { href: '#minh-bach', label: 'Minh bạch' },
    { href: '#qr', label: 'Nộp quỹ QR' },
    { href: '#tu-dong', label: 'Tự động' },
    { href: '#ai', label: 'AI đọc bill' },
    { href: '#bao-cao', label: 'Báo cáo' },
];

const highlights = [
    // Chia 2 phần: điện thoại ngắt dòng đúng chỗ này để 3 ô đều nhau, màn rộng nối thành một dòng.
    { title: 'QR', text: ['Quét mã', 'chuyển tiền'] },
    { title: 'A.I', text: ['Đọc bill', 'chuyển khoản'] },
    { title: 'FREE', text: ['Sử dụng', 'miễn phí'] },
];

const banks = [
    { name: 'VCB', color: '#0b6b3a' },
    { name: 'MB', color: '#1c3f94' },
    { name: 'TCB', color: '#e30613' },
    { name: 'BIDV', color: '#005baa' },
];
</script>

<template>
    <div class="min-h-dvh overflow-x-clip">
        <!-- Thanh trên cùng -->
        <header class="sticky top-0 z-30 border-b border-border bg-background/85 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-3 px-4">
                <BrandMark />
                <nav class="hidden items-center gap-7 text-sm font-medium text-muted-foreground lg:flex" aria-label="Các phần của trang">
                    <a v-for="item in nav" :key="item.href" :href="item.href" class="hover:text-foreground">{{ item.label }}</a>
                </nav>
                <div class="flex shrink-0 items-center gap-2">
                    <RouterLink v-if="session.me" :to="homePath()" class="btn-outline min-h-10 text-sm">Vào nhóm</RouterLink>
                    <RouterLink v-else to="/login" class="btn-primary min-h-10 text-sm">Đăng nhập</RouterLink>
                </div>
            </div>
        </header>

        <main>
            <!-- ============ HERO ============ -->
            <section class="relative overflow-hidden">
                <div class="landing-dots absolute inset-0 [mask-image:radial-gradient(ellipse_at_70%_40%,black,transparent_70%)]" aria-hidden="true"></div>
                <div class="pointer-events-none absolute -right-20 top-10 size-96 rounded-full bg-[#86efac] opacity-40 blur-3xl" aria-hidden="true"></div>
                <div class="relative mx-auto grid max-w-6xl items-center gap-12 px-4 pb-20 pt-12 lg:grid-cols-[1.05fr_1fr] lg:pt-20">
                    <div>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/15 px-3 py-1.5 text-[13px] font-semibold text-primary">
                            <Star class="size-4" /> Quỹ nhóm thế hệ mới
                        </span>
                        <h1 class="landing-display mt-5 text-[30px] font-extrabold leading-[1.1] min-[340px]:text-[34px] min-[380px]:text-[40px] sm:text-[56px]">
                            Quỹ nhóm
                            <!-- Dấu phẩy dính với "minh bạch" để không bị đẩy xuống dòng một mình. -->
                            <span class="whitespace-nowrap"><span class="relative inline-block text-primary">minh bạch<svg class="absolute -bottom-2 left-0 w-full" viewBox="0 0 200 12" preserveAspectRatio="none" aria-hidden="true"><path d="M2 9C50 3 150 1 198 7" stroke="#facc15" stroke-width="5" fill="none" stroke-linecap="round" /></svg></span>,</span><br />
                            thủ quỹ nhẹ gánh.
                        </h1>
                        <p class="mt-6 max-w-lg text-lg leading-relaxed text-muted-foreground">
                            Mỗi khoản thu chi là một bài đăng trên dòng thời gian của nhóm. Thành viên nộp quỹ bằng mã QR, ngân hàng báo về là WePo tự ghi sổ và tự làm báo cáo.
                        </p>
                        <div class="mt-8 flex flex-wrap gap-3">
                            <RouterLink v-if="session.me" :to="homePath()" class="btn-primary min-h-12 px-6">Vào nhóm <ArrowRight class="size-4" /></RouterLink>
                            <RouterLink v-else to="/groups/new" class="btn-primary min-h-12 px-6">Tạo nhóm của bạn <ArrowRight class="size-4" /></RouterLink>
                            <RouterLink v-if="demoGroup" :to="`/${demoGroup}`" class="btn-outline min-h-12 px-6">Xem nhóm mẫu</RouterLink>
                        </div>
                        <!-- Luôn một hàng 3 ô. Điện thoại: chữ mô tả 2 dòng đều nhau. Màn rộng: một dòng, ô rộng theo chữ. -->
                        <dl class="mt-10 grid max-w-xl grid-cols-3 gap-3 border-t border-border pt-6 text-[13px] leading-snug sm:flex sm:gap-x-12 sm:text-sm lg:gap-x-8 xl:gap-x-12">
                            <div v-for="item in highlights" :key="item.title">
                                <dt class="landing-display text-2xl font-bold text-primary">{{ item.title }}</dt>
                                <dd class="mt-0.5 whitespace-nowrap text-muted-foreground">{{ item.text[0] }} <br class="sm:hidden" />{{ item.text[1] }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="relative mx-auto flex justify-center py-6">
                        <svg class="landing-float-slow absolute -left-6 top-4 size-20 sm:size-24" viewBox="0 0 100 100" aria-hidden="true"><circle cx="50" cy="50" r="38" fill="#facc15" /><circle cx="50" cy="50" r="28" fill="none" stroke="#ffa20a" stroke-width="4" stroke-dasharray="4 6" /><text x="50" y="62" text-anchor="middle" font-family="Be Vietnam Pro, sans-serif" font-weight="800" font-size="34" fill="#a16207">đ</text></svg>
                        <PhoneFrame src="/landing/timeline.webp" alt="Dòng thời gian thu chi của một nhóm trên WePo" :width="300" eager status-bg="#cfe7d7" />
                        <div class="landing-pop landing-float absolute -left-2 top-[40%] flex items-center gap-3 p-3 sm:-left-16">
                            <span class="grid size-10 place-items-center rounded-xl bg-primary/15 text-lg" aria-hidden="true">🐷</span>
                            <div>
                                <p class="text-xs text-muted-foreground">Phạm Thu Hà vừa nộp quỹ</p>
                                <p class="landing-display font-bold text-income">+500.000 đ</p>
                            </div>
                        </div>
                        <div class="landing-pop landing-float-slow absolute -right-1 top-16 w-44 p-3 sm:-right-10 sm:w-48">
                            <p class="text-xs text-muted-foreground">Thu quỹ năm 2026</p>
                            <div class="mt-2 h-2 rounded-full bg-muted"><div class="h-2 w-[63%] rounded-full bg-primary"></div></div>
                            <p class="mt-1.5 text-xs"><b>15/24</b> người đã nộp</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============ 01 MINH BẠCH ============ -->
            <LandingSection
                id="minh-bach"
                number="01"
                eyebrow="Lưu trữ kỉ niệm"
                title="Mỗi khoản thu chi là một câu chuyện, cả nhóm cùng thấy."
                text="Khoản chi nào cũng là một bài đăng trên dòng thời gian, kèm ảnh sự kiện, người nhận và số dư sau đó. Thành viên thả tim, bình luận như mạng xã hội, không cần hỏi thủ quỹ “quỹ còn bao nhiêu?”."
                :points="['Ảnh sự kiện đính kèm ngay trong bài thu chi', 'Nhóm công khai cho ai có link xem, hoặc riêng tư chỉ thành viên', 'Thả tim, bình luận, chia sẻ trang nhóm một chạm']"
                class="bg-card"
            >
                <template #visual>
                    <div class="absolute inset-x-6 bottom-10 top-10 rounded-[48px] bg-gradient-to-br from-primary/15 to-[#facc15]/15" aria-hidden="true"></div>
                    <div class="landing-float-slow absolute -left-2 top-16 hidden -rotate-[8deg] rounded-lg bg-white p-2 pb-6 shadow-xl sm:block lg:-left-6" aria-hidden="true">
                        <div class="grid h-24 w-28 place-items-center rounded bg-gradient-to-br from-[#fbbf24] to-[#f97316] text-4xl">🎂</div>
                        <p class="mt-1 text-center text-[11px] font-medium text-[#4b5563]">Sinh nhật Hà</p>
                    </div>
                    <div class="landing-float absolute -right-2 bottom-28 hidden rotate-[7deg] rounded-lg bg-white p-2 pb-6 shadow-xl sm:block lg:-right-6" aria-hidden="true">
                        <div class="grid h-24 w-28 place-items-center rounded bg-gradient-to-br from-[#60a5fa] to-[#6366f1] text-4xl">🏖️</div>
                        <p class="mt-1 text-center text-[11px] font-medium text-[#4b5563]">Du lịch hè</p>
                    </div>
                    <div class="relative py-6">
                        <PhoneFrame src="/landing/timeline-post.webp" alt="Bài chi trên dòng thời gian, có ảnh sự kiện và bình luận" />
                    </div>
                    <div class="landing-pop landing-float absolute right-0 top-28 flex items-center gap-2 px-3 py-2 text-sm font-semibold sm:right-6 lg:-right-2">
                        <Heart class="size-4 fill-[#e11d48] text-[#e11d48]" /> 12
                        <span class="ml-2 inline-flex items-center gap-1 text-muted-foreground"><MessageCircle class="size-4" /> 5 bình luận</span>
                    </div>
                </template>
            </LandingSection>

            <!-- ============ 02 QR ============ -->
            <LandingSection
                id="qr"
                number="02"
                eyebrow="Nộp quỹ bằng mã QR"
                title="Quét mã chuyển khoản, nộp xong quỹ. Không còn nhắn “ai chưa nộp?”."
                text="Tạo chiến dịch thu, WePo sinh mã QR có sẵn số tiền và nội dung chuyển khoản riêng cho từng người. Trên điện thoại, thành viên mở thẳng app ngân hàng của mình để chuyển."
                :points="['QR điền sẵn số tiền, nội dung chuyển khoản theo từng thành viên', 'Danh sách Đã nộp / Chưa nộp luôn cập nhật', 'Chuyển rồi? Gửi ảnh bill để thủ quỹ xác nhận']"
                reverse
            >
                <template #background>
                    <div class="landing-dots absolute inset-0 opacity-60 [mask-image:linear-gradient(to_bottom,transparent,black_30%,black_70%,transparent)]" aria-hidden="true"></div>
                </template>
                <template #visual>
                    <svg class="absolute -left-2 top-8 size-24 text-primary opacity-80 sm:left-4 lg:-left-4" viewBox="0 0 100 100" fill="currentColor" aria-hidden="true"><rect width="38" height="38" rx="8" /><rect x="8" y="8" width="22" height="22" rx="4" class="fill-background" /><rect x="13" y="13" width="12" height="12" rx="2" /><rect x="48" width="10" height="10" rx="2" /><rect x="62" y="12" width="10" height="10" rx="2" /><rect x="48" y="26" width="10" height="10" rx="2" /><rect y="48" width="10" height="10" rx="2" /><rect x="14" y="62" width="10" height="10" rx="2" /><rect x="28" y="48" width="10" height="10" rx="2" /></svg>
                    <div class="absolute -right-6 bottom-16 size-40 rounded-full border-[14px] border-dashed border-[#facc15]/50" aria-hidden="true"></div>
                    <div class="relative py-6">
                        <PhoneFrame src="/landing/campaign.webp" alt="Trang chiến dịch thu quỹ có mã QR chuyển khoản" :width="300" />
                    </div>
                    <div class="landing-pop landing-float absolute -left-1 bottom-28 p-3 sm:left-0 lg:-left-10">
                        <p class="text-xs font-medium text-muted-foreground">Mở app ngân hàng</p>
                        <div class="mt-2 flex gap-1.5">
                            <span v-for="bank in banks" :key="bank.name" class="grid size-9 place-items-center rounded-lg text-[10px] font-bold text-white" :style="{ background: bank.color }">{{ bank.name }}</span>
                        </div>
                    </div>
                    <div class="landing-pop landing-float-slow absolute right-0 top-24 flex items-center gap-2 px-3 py-2 sm:right-4 lg:-right-8">
                        <span class="grid size-7 place-items-center rounded-full bg-primary text-primary-foreground"><Check class="size-4" /></span>
                        <span class="text-sm font-semibold">Lan đã nộp 500.000 đ</span>
                    </div>
                </template>
            </LandingSection>

            <!-- ============ 03 TỰ ĐỘNG QUA NGÂN HÀNG ============ -->
            <LandingSection
                id="tu-dong"
                number="03"
                eyebrow="Tự động qua ngân hàng"
                title="Tiền về tài khoản, sổ quỹ tự cập nhật."
                text="Kết nối tài khoản nhận quỹ với Ngân hàng. Mỗi biến động số dư được WePo đọc nội dung chuyển khoản, đoán đúng chiến dịch và người nộp, thủ quỹ chỉ cần bấm duyệt."
                :points="['Nhận biến động số dư ngay khi tiền về', 'Tự khớp người nộp theo nội dung chuyển khoản', 'Hàng chờ duyệt gọn, email tóm tắt cho thủ quỹ']"
                dark
            >
                <template #background>
                    <div class="pointer-events-none absolute -left-20 top-0 size-96 rounded-full bg-[#14a44a] opacity-40 blur-3xl" aria-hidden="true"></div>
                    <div class="pointer-events-none absolute bottom-0 right-0 size-80 rounded-full bg-[#a855f7] opacity-20 blur-3xl" aria-hidden="true"></div>
                </template>
                <template #visual>
                    <div class="relative py-6">
                        <PhoneFrame src="/landing/admin.webp" alt="Trang tổng quan quản trị nhóm: số dư quỹ và thu chi theo tháng" />
                    </div>
                    <!-- Thông báo ngân hàng → WePo ghi sổ (không nêu tên SePay trên landing) (màu cố định trên nền xanh đậm) -->
                    <div class="absolute -right-1 top-[38%] z-10 w-52 space-y-2 text-[#0b2a1f] sm:right-4 sm:w-60 lg:-right-4">
                        <div class="landing-float rounded-2xl bg-white/95 p-3 shadow-2xl">
                            <div class="flex items-center gap-2 text-[11px] text-[#4b5563]"><span class="grid size-5 place-items-center rounded bg-[#0b6b3a] text-[7px] font-bold text-white">VCB</span> Ngân hàng · vừa xong</div>
                            <p class="mt-1 text-sm"><b class="text-[#15803d]">+500.000 đ</b> · NGUYEN THI LAN NHOMDEN26</p>
                        </div>
                        <p class="ml-6 inline-flex items-center gap-1.5 rounded-full bg-[#0a5541] px-2.5 py-1 text-xs font-medium text-[#86efac] shadow-lg"><ArrowDown class="size-4" /> Ngân hàng báo về WePo</p>
                        <div class="landing-float-slow rounded-2xl bg-white p-3 shadow-2xl">
                            <div class="flex items-center gap-2"><img src="/brand/wepo-mark.svg" alt="" class="size-6" /><p class="text-sm font-semibold">Đã ghi vào “Thu quỹ 2026”</p></div>
                            <p class="mt-1 text-xs text-[#4b5563]">Nguyễn Thị Lan · Đã nộp</p>
                        </div>
                    </div>
                </template>
            </LandingSection>

            <!-- ============ 04 AI ĐỌC BILL ============ -->
            <LandingSection
                id="ai"
                number="04"
                eyebrow="AI đọc bill chuyển khoản"
                title="Chụp bill, AI điền phiếu. Thủ quỹ chỉ việc lưu."
                text="Thành viên gửi ảnh chụp màn hình chuyển khoản, hoặc thủ quỹ tải bill lên khi tạo phiếu. AI đọc số tiền, ngày giờ, người chuyển và nội dung rồi điền sẵn vào phiếu thu chi."
                :points="['Đọc ảnh chụp bill chuyển khoản của các app ngân hàng', 'Tự gợi ý loại thu chi và người trong danh bạ nhóm', 'Ô do AI điền có nhãn riêng để thủ quỹ kiểm lại']"
                reverse
                class="bg-card"
            >
                <template #visual>
                    <div class="absolute inset-x-4 bottom-12 top-12 rounded-[48px] bg-gradient-to-br from-[#a855f7]/15 to-primary/15" aria-hidden="true"></div>
                    <!-- Bill giả lập có tia quét (màu cố định như ảnh chụp bill) -->
                    <div class="landing-float-slow absolute -left-1 top-16 z-10 w-40 -rotate-6 overflow-hidden rounded-2xl border border-[#d9dedb] bg-white p-3 text-[#111827] shadow-2xl sm:left-2 sm:w-44 lg:-left-12" aria-hidden="true">
                        <div class="landing-scan absolute inset-x-0 h-0.5 bg-[#a855f7] shadow-[0_0_14px_4px_rgb(168_85_247_/_0.45)]"></div>
                        <div class="mx-auto grid size-10 place-items-center rounded-full bg-[#dcfce7] text-[#15803d]"><Check class="size-5" /></div>
                        <p class="mt-2 text-center text-[10px] text-[#4b5563]">Chuyển tiền thành công</p>
                        <p class="landing-display text-center text-lg font-bold">500.000 VND</p>
                        <dl class="mt-2 space-y-1 text-[10px] text-[#4b5563]">
                            <div class="flex justify-between gap-2"><dt>Người chuyển</dt><dd class="text-right font-bold text-[#111827]">NGUYEN THI LAN</dd></div>
                            <div class="flex justify-between gap-2"><dt>Thời gian</dt><dd class="font-bold text-[#111827]">09/11 20:15</dd></div>
                        </dl>
                    </div>
                    <Sparkles class="landing-float absolute right-2 top-6 z-10 size-10 text-[#a855f7] sm:right-10 lg:-right-2" aria-hidden="true" />
                    <div class="relative py-6">
                        <PhoneFrame src="/landing/transaction-form.webp" alt="Phiếu thu được AI điền sẵn từ ảnh bill chuyển khoản" :width="300" />
                    </div>
                    <div class="landing-pop landing-float absolute -right-1 bottom-32 flex items-center gap-2 px-3 py-2 sm:right-2 lg:-right-12">
                        <span class="rounded-md bg-[#a855f7]/15 px-1.5 py-0.5 text-[11px] font-bold text-[#7e22ce] dark:text-[#d8b4fe]">AI</span>
                        <span class="text-sm font-semibold">AI đã điền sẵn phiếu</span>
                    </div>
                </template>
            </LandingSection>

            <!-- ============ 05 BÁO CÁO ============ -->
            <LandingSection
                id="bao-cao"
                number="05"
                eyebrow="Báo cáo tự tổng hợp"
                title="Cuối năm không còn ngồi cộng sổ."
                text="Số dư, thu chi theo tháng, chi theo loại và tiến độ từng chiến dịch luôn có sẵn. Cần gửi cho cả nhóm? Xuất Excel một chạm, đủ sổ quỹ đầu kỳ, cuối kỳ."
                :points="['Thống kê theo tháng, năm hoặc khoảng tùy chọn', 'Chi theo loại: liên hoan, đám hiếu, đám hỉ, thăm ốm…', 'Xuất Excel: sổ quỹ, theo loại, theo tháng, chiến dịch']"
            >
                <template #background>
                    <div class="pointer-events-none absolute left-1/3 top-20 size-96 rounded-full bg-[#fde047] opacity-20 blur-3xl" aria-hidden="true"></div>
                </template>
                <template #visual>
                    <div class="relative h-[480px] w-full max-w-[460px] sm:h-[680px]">
                        <svg class="absolute -right-2 bottom-16 h-28 w-40 opacity-70" viewBox="0 0 160 110" aria-hidden="true"><rect x="6" y="60" width="18" height="44" rx="4" fill="#86efac" /><rect x="34" y="36" width="18" height="68" rx="4" fill="#14a44a" /><rect x="62" y="72" width="18" height="32" rx="4" fill="#fca5a5" /><rect x="90" y="20" width="18" height="84" rx="4" fill="#15803d" /><rect x="118" y="50" width="18" height="54" rx="4" fill="#f87171" /></svg>
                        <div class="absolute left-0 top-16 -rotate-6 sm:left-2">
                            <PhoneFrame src="/landing/stats.webp" alt="Thống kê thu chi theo tháng và chi theo loại" :width="250" :mobile-width="185" />
                        </div>
                        <div class="absolute right-0 top-0 rotate-[5deg] sm:right-2">
                            <PhoneFrame src="/landing/reports.webp" alt="Báo cáo sổ quỹ có nút xuất Excel" :width="250" :mobile-width="185" />
                        </div>
                        <div class="landing-pop landing-float absolute bottom-4 left-1/2 flex w-max max-w-[92%] -translate-x-1/2 items-center gap-3 px-4 py-3">
                            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-[#107c41] text-[11px] font-bold text-white">XLSX</span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold">wepo-nhomdemo-so-quy-2026.xlsx</p>
                                <p class="text-xs text-muted-foreground">Sổ quỹ đầu kỳ, cuối kỳ</p>
                            </div>
                        </div>
                    </div>
                </template>
            </LandingSection>

            <!-- ============ CTA ============ -->
            <section class="px-4 pb-20">
                <div class="relative mx-auto max-w-6xl overflow-hidden rounded-[32px] bg-gradient-to-br from-[#0f7d60] via-[#15803d] to-[#0a5541] px-6 py-16 text-center text-white sm:px-16">
                    <div class="landing-dots absolute inset-0 opacity-40" aria-hidden="true"></div>
                    <svg class="absolute -left-6 -top-6 size-28 sm:size-32" viewBox="0 0 100 100" aria-hidden="true"><circle cx="50" cy="50" r="38" fill="#facc15" /><text x="50" y="64" text-anchor="middle" font-family="Be Vietnam Pro, sans-serif" font-weight="800" font-size="38" fill="#a16207">đ</text></svg>
                    <img src="/brand/wepo-mark.svg" alt="" class="relative mx-auto size-16" />
                    <h2 class="landing-display relative mx-auto mt-5 max-w-2xl text-3xl font-bold leading-tight sm:text-5xl">Lập quỹ cho nhóm của bạn ngay hôm nay</h2>
                    <p class="relative mx-auto mt-4 max-w-xl text-lg text-white/80">Lớp cũ, hội bạn thân, đội bóng, câu lạc bộ… nhóm nào có quỹ chung là dùng được.</p>
                    <div class="relative mt-8 flex flex-wrap justify-center gap-3">
                        <RouterLink v-if="session.me" :to="homePath()" class="btn min-h-12 bg-white px-6 text-[#0a5541] hover:bg-white/90">Vào nhóm</RouterLink>
                        <template v-else>
                            <RouterLink to="/groups/new" class="btn min-h-12 bg-white px-6 text-[#0a5541] hover:bg-white/90">Tạo nhóm</RouterLink>
                            <RouterLink to="/login" class="btn min-h-12 border border-white/40 px-6 text-white hover:bg-white/10">Đăng nhập</RouterLink>
                        </template>
                    </div>
                    <p v-if="!session.me" class="relative mt-5 text-sm text-white/80">Chưa có tài khoản? <RouterLink to="/register" class="font-semibold text-white underline-offset-2 hover:underline">Tạo tài khoản</RouterLink></p>
                </div>
            </section>
        </main>

        <footer class="border-t border-border py-8 text-center text-sm text-muted-foreground">
            <p><RouterLink to="/" class="font-semibold text-foreground hover:text-primary hover:underline">WePo</RouterLink> © {{ year }} · Quản lý quỹ nhóm cùng nhau</p>
            <p class="mt-1">Tài trợ bởi <a href="https://hipotech.vn" target="_blank" rel="noopener" class="font-semibold text-foreground hover:text-primary hover:underline">HIPOTECH</a></p>
        </footer>
    </div>
</template>
