import { toast } from './notice.js';

export async function copyText(text, done = 'Đã chép.') {
    try {
        await navigator.clipboard.writeText(text);
        toast(done);
    } catch {
        toast('Không chép được, bạn bôi đen rồi chép tay nhé.', 'error');
    }
}
