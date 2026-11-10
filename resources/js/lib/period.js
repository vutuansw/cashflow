import { computed, reactive } from 'vue';

// Kỳ xem dùng chung cho các màn có lọc theo thời gian: Tháng / Năm / Tất cả, lùi tiến từng kỳ bằng ‹ ›.
// Trên URL: ?ky=month|year|all (mặc định defaultKind, không ghi) và ?moc=YYYY-MM hoặc YYYY (kỳ hiện tại thì không ghi).
const pad = (n) => String(n).padStart(2, '0');

export const PERIOD_KINDS = [
    { v: 'month', l: 'Tháng' },
    { v: 'year', l: 'Năm' },
    { v: 'all', l: 'Tất cả' },
];

export function usePeriod(query = {}, { defaultKind = 'year', kinds = ['month', 'year', 'all'] } = {}) {
    const today = new Date();
    const [y, m] = String(query.moc ?? '').split('-').map(Number);
    const state = reactive({
        kind: kinds.includes(query.ky) ? query.ky : defaultKind,
        year: y || today.getFullYear(),
        month: m || today.getMonth() + 1,
    });

    const isCurrent = computed(() => state.year === today.getFullYear() && (state.kind !== 'month' || state.month === today.getMonth() + 1));

    const label = computed(() => {
        if (state.kind === 'month') return `Tháng ${pad(state.month)}/${state.year}`;
        if (state.kind === 'year') return `Năm ${state.year}`;
        return 'Toàn bộ thời gian';
    });

    // Không cho tiến quá kỳ hiện tại (chưa có số liệu ở tương lai).
    const canNext = computed(() => state.kind !== 'all' && !isCurrent.value && (state.year < today.getFullYear() || state.month < today.getMonth() + 1 || state.kind === 'year'));

    const range = computed(() => {
        const { kind, year, month } = state;
        if (kind === 'month') return { from: `${year}-${pad(month)}-01`, to: `${year}-${pad(month)}-${pad(new Date(year, month, 0).getDate())}` };
        if (kind === 'year') return { from: `${year}-01-01`, to: `${year}-12-31` };
        return {};
    });

    function shift(step) {
        if (state.kind === 'year') {
            state.year += step;
            return;
        }
        const d = new Date(state.year, state.month - 1 + step, 1);
        state.year = d.getFullYear();
        state.month = d.getMonth() + 1;
    }

    // Đổi kiểu kỳ thì về kỳ hiện tại, tránh đang xem tháng 3 năm ngoái bấm "Năm" lại ra năm ngoái mà không để ý.
    function setKind(kind) {
        Object.assign(state, { kind, year: today.getFullYear(), month: today.getMonth() + 1 });
    }

    // Phần query để ghi lên URL.
    const toQuery = () => ({
        ky: state.kind === defaultKind ? '' : state.kind,
        moc: state.kind === 'all' || isCurrent.value ? '' : state.kind === 'month' ? `${state.year}-${pad(state.month)}` : String(state.year),
    });

    return { state, label, canNext, range, shift, setKind, toQuery, options: PERIOD_KINDS.filter((k) => kinds.includes(k.v)) };
}
