<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    items: {
        type: Array,
        required: true,
    },
    rotation: {
        type: Number,
        default: 0,
    },
    size: {
        type: Number,
        default: 300,
    },
});

const canvasRef = ref(null);
const fallbackPalette = ['#6366f1', '#8b5cf6', '#a78bfa', '#7c3aed', '#4f46e5', '#818cf8', '#6d28d9', '#c4b5fd'];
// 中心ハブに文字が被らないよう、ラベル開始位置を中心から固定距離だけ離す
const labelGapFromCenter = 60;
const labelOuterInset = 14;
// 白文字と濃い文字の contrast ratio が入れ替わる相対輝度
const labelInkThreshold = 0.2;

const pointerSize = computed(() => ({
    side: props.size > 300 ? 13 : 10,
    height: props.size > 300 ? 30 : 24,
    offset: props.size > 300 ? -6 : -4,
}));

const hubRadius = computed(() => (props.size > 300 ? 28 : 22));

function readTheme() {
    const style = getComputedStyle(document.documentElement);
    const token = (name, fallback) => style.getPropertyValue(name).trim() || fallback;
    const palette = token('--wheel-palette', '')
        .split(',')
        .map((color) => color.trim())
        .filter(Boolean);

    return {
        palette: palette.length > 0 ? palette : fallbackPalette,
        line: token('--wheel-line', '#0f0f13'),
        hub: token('--wheel-hub', '#0f0f13'),
        hubBorder: token('--overlay-border', 'rgba(255,255,255,0.15)'),
        empty: token('--wheel-empty', '#22222d'),
        emptyText: token('--text-muted', 'rgba(255,255,255,0.5)'),
        onAccent: token('--on-accent', '#ffffff'),
        ink: token('--wheel-ink', '#14141c'),
    };
}

function relativeLuminance(hex) {
    const normalized = hex.replace('#', '');
    const expanded = normalized.length === 3
        ? [...normalized].map((char) => char + char).join('')
        : normalized;

    if (expanded.length !== 6) {
        return 0;
    }

    const [r, g, b] = [0, 2, 4]
        .map((start) => parseInt(expanded.slice(start, start + 2), 16) / 255)
        .map((channel) => (channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4));

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

function labelColor(sliceColor, theme) {
    return relativeLuminance(sliceColor) > labelInkThreshold ? theme.ink : theme.onAccent;
}

function fitLabel(ctx, text, maxWidth) {
    if (maxWidth <= 0) {
        return '';
    }

    if (ctx.measureText(text).width <= maxWidth) {
        return text;
    }

    const ellipsis = '…';
    const ellipsisWidth = ctx.measureText(ellipsis).width;
    if (ellipsisWidth >= maxWidth) {
        return ellipsis;
    }

    let low = 0;
    let high = text.length;
    while (low < high) {
        const mid = Math.ceil((low + high) / 2);
        if (ctx.measureText(text.slice(0, mid)).width + ellipsisWidth <= maxWidth) {
            low = mid;
        } else {
            high = mid - 1;
        }
    }

    return `${text.slice(0, low)}${ellipsis}`;
}

function draw() {
    const canvas = canvasRef.value;
    if (!canvas) {
        return;
    }

    const ctx = canvas.getContext('2d');
    if (!ctx) {
        return;
    }

    const width = canvas.width;
    const height = canvas.height;
    const cx = width / 2;
    const cy = height / 2;
    const radius = Math.min(width, height) / 2 - 12;

    ctx.clearRect(0, 0, width, height);

    const theme = readTheme();

    if (props.items.length === 0) {
        ctx.fillStyle = theme.empty;
        ctx.beginPath();
        ctx.arc(cx, cy, radius, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = theme.emptyText;
        ctx.font = `${props.size > 300 ? 16 : 13}px Inter`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('景品を追加してください', cx, cy);
        return;
    }

    const slice = (Math.PI * 2) / props.items.length;
    const offset = (props.rotation * Math.PI) / 180;

    props.items.forEach((item, index) => {
        const start = offset + index * slice;
        const end = start + slice;
        const sliceColor = theme.palette[index % theme.palette.length];

        ctx.beginPath();
        ctx.moveTo(cx, cy);
        ctx.arc(cx, cy, radius, start, end);
        ctx.closePath();
        ctx.fillStyle = sliceColor;
        ctx.fill();
        ctx.strokeStyle = theme.line;
        ctx.lineWidth = 2;
        ctx.stroke();

        ctx.save();
        ctx.translate(cx, cy);
        ctx.rotate(start + slice / 2);
        ctx.textAlign = 'left';
        ctx.textBaseline = 'middle';
        ctx.fillStyle = labelColor(sliceColor, theme);
        const fontSize = Math.min(props.size > 300 ? 15 : 13, (radius * 0.5) / props.items.length + 8);
        ctx.font = `bold ${fontSize}px Inter`;
        const innerOffset = Math.max(labelGapFromCenter, hubRadius.value + 12);
        const labelMaxWidth = radius - labelOuterInset - innerOffset;
        ctx.fillText(fitLabel(ctx, item, labelMaxWidth), innerOffset, 0);
        ctx.restore();
    });

    ctx.beginPath();
    ctx.arc(cx, cy, hubRadius.value, 0, Math.PI * 2);
    ctx.fillStyle = theme.hub;
    ctx.fill();
    ctx.strokeStyle = theme.hubBorder;
    ctx.lineWidth = 2;
    ctx.stroke();
}

watch(() => [props.items, props.rotation, props.size], draw, { deep: true });

let themeObserver;

onMounted(() => {
    themeObserver = new MutationObserver(draw);
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
});

onBeforeUnmount(() => themeObserver?.disconnect());

function onCanvasReady(el) {
    canvasRef.value = el;
    if (el) {
        draw();
    }
}
</script>

<template>
    <div class="relative flex items-center justify-center">
        <div
            class="absolute top-0 left-1/2 z-10 -translate-x-1/2"
            :style="{ marginTop: `${pointerSize.offset}px` }"
        >
            <div
                :style="{
                    width: 0,
                    height: 0,
                    borderLeft: `${pointerSize.side}px solid transparent`,
                    borderRight: `${pointerSize.side}px solid transparent`,
                    borderTop: `${pointerSize.height}px solid #f59e0b`,
                    filter: 'drop-shadow(0 2px 4px rgba(0,0,0,0.5))',
                }"
            />
        </div>
        <canvas
            :ref="onCanvasReady"
            :width="size"
            :height="size"
            class="rounded-full"
        />
    </div>
</template>
