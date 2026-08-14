/* Toast de feedback (canto inferior direito). */

import { $ } from './dom.js';

let timer = null;

export function toast(message, kind = 'success') {
    const box = $('toast');
    box.textContent = message;
    box.className = `show ${kind}`;

    clearTimeout(timer);
    timer = setTimeout(() => { box.className = ''; }, 3500);
}
