<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    rankings: {
        type: Array,
        required: true,
    },
});

const emit = defineEmits(['close']);

// ステップ管理:
// 0: 開始前（準備画面）
// 1: 4位以下発表
// 2: 3位発表（銅メダル）
// 3: 2位発表（銀メダル）
// 4: 1位前のドラムロール／溜め
// 5: 1位（優勝）発表 ＋ 紙吹雪
const step = ref(0);
const isAutoPlay = ref(false);
const canvasRef = ref(null);

let autoPlayTimer = null;
let confettiRafId = null;
let particles = [];

const firstPlace = computed(() => props.rankings[0] ?? null);
const secondPlace = computed(() => props.rankings[1] ?? null);
const thirdPlace = computed(() => props.rankings[2] ?? null);
const otherPlaces = computed(() => props.rankings.slice(3));

const hasFirst = computed(() => props.rankings.length >= 1);
const hasSecond = computed(() => props.rankings.length >= 2);
const hasThird = computed(() => props.rankings.length >= 3);
const hasFourthOrBelow = computed(() => props.rankings.length > 3);

// 各要素の表示判定
const showOtherPlaces = computed(() => step.value >= 1);
const showThird = computed(() => step.value >= 2);
const showSecond = computed(() => step.value >= 3);
const isDrumRoll = computed(() => step.value === 4);
const showFirst = computed(() => step.value >= 5);

function getNextStepTarget(current) {
    if (current === 0) {
        if (hasFourthOrBelow.value) return 1;
        if (hasThird.value) return 2;
        if (hasSecond.value) return 3;
        return 4;
    }
    if (current === 1) {
        if (hasThird.value) return 2;
        if (hasSecond.value) return 3;
        return 4;
    }
    if (current === 2) {
        if (hasSecond.value) return 3;
        return 4;
    }
    if (current === 3) {
        return 4;
    }
    if (current === 4) {
        return 5;
    }
    return 5;
}

function nextStep() {
    if (step.value < 5) {
        setStep(getNextStepTarget(step.value));
    }
}

function skipToEnd() {
    stopAutoPlay();
    setStep(5);
}

function restart() {
    stopAutoPlay();
    stopConfetti();
    step.value = 0;
}

function setStep(newStep) {
    step.value = newStep;

    if (newStep === 5) {
        nextTick(() => {
            startConfetti();
        });
        stopAutoPlay();
    }
}

// 自動再生ループ
function toggleAutoPlay() {
    if (isAutoPlay.value) {
        stopAutoPlay();
    } else {
        startAutoPlay();
    }
}

function startAutoPlay() {
    isAutoPlay.value = true;
    runAutoPlayNext();
}

function stopAutoPlay() {
    isAutoPlay.value = false;
    if (autoPlayTimer) {
        clearTimeout(autoPlayTimer);
        autoPlayTimer = null;
    }
}

function runAutoPlayNext() {
    if (!isAutoPlay.value) return;

    if (step.value >= 5) {
        stopAutoPlay();
        return;
    }

    // ドラムロールの溜め時間は2.2秒、通常ステップは2.6秒
    const delay = step.value === 4 ? 2200 : (step.value === 0 ? 800 : 2600);

    autoPlayTimer = setTimeout(() => {
        if (!isAutoPlay.value) return;
        nextStep();
        if (step.value < 5) {
            runAutoPlayNext();
        } else {
            stopAutoPlay();
        }
    }, delay);
}

// 紙吹雪（Confetti）エンジン
class ConfettiParticle {
    constructor(w, h, originX) {
        this.w = w;
        this.h = h;
        this.x = originX !== undefined ? originX : Math.random() * w;
        this.y = originX !== undefined ? h * 0.7 : Math.random() * (h * 0.5) - 20;
        this.size = Math.random() * 9 + 6;
        this.color = [
            '#fbbf24', '#f59e0b', '#6366f1', '#a855f7',
            '#ec4899', '#10b981', '#38bdf8', '#f43f5e', '#ffffff',
        ][Math.floor(Math.random() * 9)];
        this.vx = (Math.random() - 0.5) * 16 + (originX !== undefined ? (originX < w / 2 ? 5 : -5) : 0);
        this.vy = -(Math.random() * 16 + 10);
        this.gravity = 0.2;
        this.rotation = Math.random() * 360;
        this.rotationSpeed = (Math.random() - 0.5) * 10;
        this.wobble = Math.random() * 10;
        this.wobbleSpeed = Math.random() * 0.12 + 0.05;
        this.opacity = 1;
    }

    update(w, h) {
        this.x += this.vx;
        this.y += this.vy;
        this.vy += this.gravity;
        this.vx *= 0.985;
        this.rotation += this.rotationSpeed;
        this.wobble += this.wobbleSpeed;

        if (this.y > h + 30) {
            this.y = -20;
            this.x = Math.random() * w;
            this.vy = Math.random() * 4 + 2;
            this.vx = (Math.random() - 0.5) * 3;
        }
    }

    draw(ctx) {
        ctx.save();
        ctx.translate(this.x + Math.sin(this.wobble) * 6, this.y);
        ctx.rotate((this.rotation * Math.PI) / 180);
        ctx.fillStyle = this.color;
        ctx.globalAlpha = this.opacity;
        ctx.fillRect(-this.size / 2, -this.size / 4, this.size, this.size * 0.6);
        ctx.restore();
    }
}

function startConfetti() {
    const canvas = canvasRef.value;
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const w = (canvas.width = window.innerWidth);
    const h = (canvas.height = window.innerHeight);

    particles = [];
    const count = 160;

    // 左右と中央下部からクラッカーのように発射
    for (let i = 0; i < count; i++) {
        const originX = i % 3 === 0 ? w * 0.2 : (i % 3 === 1 ? w * 0.8 : w * 0.5);
        particles.push(new ConfettiParticle(w, h, originX));
    }

    function loop() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        for (const p of particles) {
            p.update(canvas.width, canvas.height);
            p.draw(ctx);
        }
        confettiRafId = requestAnimationFrame(loop);
    }

    if (confettiRafId) {
        cancelAnimationFrame(confettiRafId);
    }
    confettiRafId = requestAnimationFrame(loop);
}

function stopConfetti() {
    if (confettiRafId) {
        cancelAnimationFrame(confettiRafId);
        confettiRafId = null;
    }
    if (canvasRef.value) {
        const ctx = canvasRef.value.getContext('2d');
        ctx?.clearRect(0, 0, canvasRef.value.width, canvasRef.value.height);
    }
    particles = [];
}

function onResize() {
    if (canvasRef.value && confettiRafId) {
        canvasRef.value.width = window.innerWidth;
        canvasRef.value.height = window.innerHeight;
    }
}

function onKey(event) {
    if (event.key === 'Escape') {
        emit('close');
        return;
    }
    if (event.key === ' ' || event.key === 'Enter') {
        if (event.target && ['INPUT', 'TEXTAREA', 'BUTTON'].includes(event.target.tagName)) {
            return;
        }
        event.preventDefault();
        if (step.value < 5) {
            nextStep();
        }
    }
}

onMounted(() => {
    window.addEventListener('keydown', onKey);
    window.addEventListener('resize', onResize);
});

onBeforeUnmount(() => {
    stopAutoPlay();
    stopConfetti();
    window.removeEventListener('keydown', onKey);
    window.removeEventListener('resize', onResize);
});
</script>

<template>
    <Teleport to="body">
        <div
            class="fixed inset-0 z-50 flex flex-col justify-between overflow-y-auto px-3 py-3 sm:px-6 sm:py-5 select-none"
            style="background: var(--scrim); backdrop-filter: blur(12px)"
        >
        <!-- 紙吹雪用 Canvas -->
        <canvas
            ref="canvasRef"
            class="pointer-events-none absolute inset-0 z-20 h-full w-full"
        />

        <!-- トップヘッダーバー -->
        <div class="relative z-30 flex items-center justify-between px-2 sm:px-6">
            <div class="flex items-center gap-2.5">
                <span class="text-2xl">🏆</span>
                <div>
                    <h1
                        class="text-base font-bold tracking-wider sm:text-xl"
                        style="font-family: Outfit, sans-serif; color: var(--text)"
                    >
                        Party Board 表彰式
                    </h1>
                    <p class="text-xs" style="color: var(--text-faint)">
                        総合スコアランキング
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="flex h-10 w-10 items-center justify-center rounded-full text-lg transition-transform hover:scale-105 active:scale-95"
                    style="background: var(--overlay); color: var(--text); border: 1px solid var(--overlay-border)"
                    title="閉じる (Esc)"
                    @click="emit('close')"
                >
                    ✕
                </button>
            </div>
        </div>

        <!-- メインステージ（ポディウム & 表彰エリア） -->
        <div class="relative z-10 mx-auto flex w-full max-w-5xl flex-1 flex-col items-center justify-center px-2 py-4">
            <!-- Step 0: 開始前案内 -->
            <div
                v-if="step === 0"
                class="flex flex-col items-center gap-6 text-center animate-fade-in"
            >
                <div class="relative">
                    <span class="text-7xl sm:text-8xl animate-bounce">🏆</span>
                </div>
                <div class="max-w-md space-y-2">
                    <h2
                        class="text-2xl font-extrabold tracking-wide sm:text-4xl"
                        style="font-family: Outfit, sans-serif; color: var(--text)"
                    >
                        表彰式をはじめます
                    </h2>
                    <p class="text-sm sm:text-base" style="color: var(--text-muted)">
                        全ゲームの合計得点から総合順位を発表します。<br class="hidden sm:inline">
                        「次へ」または「自動再生」で順次発表されます。
                    </p>
                </div>

                <div class="flex flex-wrap items-center justify-center gap-3 pt-2">
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-2xl px-8 py-3.5 text-base font-bold transition-all hover:scale-105 active:scale-95 sm:text-lg"
                        style="background: var(--indigo); color: var(--on-accent); box-shadow: 0 0 24px rgba(99, 102, 241, 0.4)"
                        @click="nextStep"
                    >
                        <span>表彰式を開始</span>
                        <span>▶</span>
                    </button>
                    <button
                        type="button"
                        class="rounded-2xl px-6 py-3.5 text-sm font-semibold transition-all hover:scale-105"
                        style="background: var(--overlay); color: var(--text-subtle); border: 1px solid var(--overlay-border)"
                        @click="toggleAutoPlay"
                    >
                        自動で進行する
                    </button>
                </div>
            </div>

            <!-- Step 1〜5: 表彰台（ポディウム）エリア -->
            <div
                v-else
                class="flex w-full flex-col items-center justify-center gap-6"
            >
                <!-- 3大トップ表彰台 -->
                <div class="flex w-full max-w-3xl items-end justify-center gap-3 sm:gap-6 pt-4">
                    <!-- 第2位（左・銀メダル） -->
                    <div
                        v-if="hasSecond"
                        class="flex w-1/3 max-w-[220px] flex-col items-center transition-all duration-700"
                        :class="[
                            showSecond
                                ? 'opacity-100 translate-y-0 scale-100'
                                : 'opacity-0 translate-y-16 scale-90 pointer-events-none'
                        ]"
                    >
                        <!-- チーム情報バッジ -->
                        <div class="mb-2 flex w-full flex-col items-center text-center sm:mb-3">
                            <span class="text-3xl sm:text-5xl drop-shadow-md">🥈</span>
                            <span
                                class="ceremony-silver-name mt-1 w-full truncate text-xs font-bold sm:mt-2 sm:text-lg"
                                style="font-family: Outfit, sans-serif"
                                :title="secondPlace?.teamName"
                            >
                                {{ secondPlace?.teamName }}
                            </span>
                            <span
                                class="ceremony-silver-pts text-xs font-semibold sm:text-base"
                                style="font-family: DM Mono, monospace"
                            >
                                {{ secondPlace?.total.toLocaleString() }} pt
                            </span>
                        </div>
                        <!-- ポディウム柱（2段目） -->
                        <div
                            class="ceremony-silver-podium relative flex h-24 w-full flex-col items-center justify-start rounded-t-2xl pt-2 shadow-xl sm:h-36 sm:rounded-t-3xl sm:pt-4 md:h-40"
                            style="
                                background: linear-gradient(180deg, rgba(148, 163, 184, 0.35) 0%, rgba(100, 116, 139, 0.15) 100%);
                                border: 1px solid rgba(203, 213, 225, 0.4);
                                border-bottom: none;
                            "
                        >
                            <span
                                class="ceremony-silver-num text-3xl font-extrabold tracking-tighter opacity-80 sm:text-5xl md:text-6xl"
                                style="font-family: Outfit, sans-serif"
                            >
                                2
                            </span>
                            <span class="ceremony-silver-label text-[10px] font-semibold tracking-wider uppercase sm:text-xs">
                                2nd Place
                            </span>
                        </div>
                    </div>

                    <!-- 第1位（中央・最高段・金メダル） -->
                    <div
                        v-if="hasFirst"
                        class="flex w-1/3 max-w-[260px] flex-col items-center transition-all duration-700"
                    >
                        <!-- ドラムロール・溜め演出中 -->
                        <div
                            v-if="isDrumRoll"
                            class="mb-3 flex w-full flex-col items-center text-center animate-pulse"
                        >
                            <span class="text-5xl sm:text-6xl animate-bounce">👑</span>
                            <span
                                class="mt-2 text-sm font-bold tracking-wider sm:text-lg"
                                style="color: #fbbf24; font-family: Outfit, sans-serif"
                            >
                                栄えある第 1 位は…？！
                            </span>
                            <span class="text-xs" style="color: var(--text-faint)">
                                ドラムロール 🥁
                            </span>
                        </div>

                        <!-- 1位発表後 -->
                        <div
                            v-else-if="showFirst"
                            class="mb-2 flex w-full flex-col items-center text-center animate-bounce-short sm:mb-3"
                        >
                            <div class="relative">
                                <span class="text-4xl sm:text-6xl drop-shadow-xl">👑</span>
                                <span class="absolute -bottom-1 -right-1 text-xl sm:text-3xl">🥇</span>
                            </div>
                            <span
                                class="ceremony-gold-name mt-1 w-full truncate text-sm font-extrabold sm:mt-2 sm:text-2xl"
                                style="font-family: Outfit, sans-serif; text-shadow: 0 0 20px rgba(251, 191, 36, 0.5)"
                                :title="firstPlace?.teamName"
                            >
                                {{ firstPlace?.teamName }}
                            </span>
                            <span
                                class="ceremony-gold-pts text-xs font-bold sm:text-xl"
                                style="font-family: DM Mono, monospace"
                            >
                                {{ firstPlace?.total.toLocaleString() }} pt
                            </span>
                        </div>

                        <!-- 1位待機中（まだ1位のステップに達していない場合） -->
                        <div
                            v-else
                            class="mb-2 flex w-full flex-col items-center text-center opacity-40 sm:mb-3"
                        >
                            <span class="text-3xl sm:text-5xl">👑</span>
                            <span class="mt-1 text-[11px] font-semibold sm:mt-2 sm:text-sm" style="color: var(--text-faint)">
                                第 1 位
                            </span>
                            <span class="text-[10px] sm:text-xs" style="color: var(--text-faint)">？？？ pt</span>
                        </div>

                        <!-- ポディウム柱（1段目・最高峰） -->
                        <div
                            class="relative flex h-36 w-full flex-col items-center justify-start rounded-t-2xl pt-2 shadow-2xl transition-all duration-500 sm:h-48 sm:rounded-t-3xl sm:pt-4 md:h-52"
                            :style="{
                                background: showFirst
                                    ? 'linear-gradient(180deg, rgba(251, 191, 36, 0.45) 0%, rgba(217, 119, 6, 0.2) 100%)'
                                    : 'linear-gradient(180deg, rgba(255, 255, 255, 0.15) 0%, rgba(255, 255, 255, 0.05) 100%)',
                                border: showFirst ? '2px solid rgba(251, 191, 36, 0.8)' : '1px solid var(--border)',
                                borderBottom: 'none',
                                boxShadow: showFirst ? '0 0 40px rgba(251, 191, 36, 0.35)' : 'none',
                            }"
                        >
                            <span
                                class="text-4xl font-black tracking-tighter sm:text-6xl md:text-7xl"
                                :style="{
                                    fontFamily: 'Outfit, sans-serif',
                                    color: showFirst ? '#fbbf24' : 'var(--text-faint)',
                                }"
                            >
                                1
                            </span>
                            <span
                                class="text-[10px] font-bold tracking-widest uppercase sm:text-xs"
                                :style="{ color: showFirst ? '#fde68a' : 'var(--text-faint)' }"
                            >
                                Champion
                            </span>
                        </div>
                    </div>

                    <!-- 第3位（右・銅メダル） -->
                    <div
                        v-if="hasThird"
                        class="flex w-1/3 max-w-[220px] flex-col items-center transition-all duration-700"
                        :class="[
                            showThird
                                ? 'opacity-100 translate-y-0 scale-100'
                                : 'opacity-0 translate-y-16 scale-90 pointer-events-none'
                        ]"
                    >
                        <!-- チーム情報バッジ -->
                        <div class="mb-2 flex w-full flex-col items-center text-center sm:mb-3">
                            <span class="text-3xl sm:text-5xl drop-shadow-md">🥉</span>
                            <span
                                class="mt-1 w-full truncate text-xs font-bold sm:mt-2 sm:text-lg"
                                style="color: var(--text); font-family: Outfit, sans-serif"
                                :title="thirdPlace?.teamName"
                            >
                                {{ thirdPlace?.teamName }}
                            </span>
                            <span
                                class="ceremony-bronze-pts text-xs font-semibold sm:text-base"
                                style="font-family: DM Mono, monospace"
                            >
                                {{ thirdPlace?.total.toLocaleString() }} pt
                            </span>
                        </div>
                        <!-- ポディウム柱（3段目） -->
                        <div
                            class="relative flex h-20 w-full flex-col items-center justify-start rounded-t-2xl pt-2 shadow-xl sm:h-28 sm:rounded-t-3xl sm:pt-4 md:h-32"
                            style="
                                background: linear-gradient(180deg, rgba(217, 119, 6, 0.35) 0%, rgba(180, 83, 9, 0.15) 100%);
                                border: 1px solid rgba(245, 158, 11, 0.4);
                                border-bottom: none;
                            "
                        >
                            <span
                                class="ceremony-bronze-num text-3xl font-extrabold tracking-tighter opacity-80 sm:text-5xl md:text-6xl"
                                style="font-family: Outfit, sans-serif"
                            >
                                3
                            </span>
                            <span class="text-[10px] font-semibold tracking-wider text-amber-500 uppercase sm:text-xs">
                                3rd Place
                            </span>
                        </div>
                    </div>
                </div>

                <!-- 第4位以下のチーム一覧エリア -->
                <div
                    v-if="hasFourthOrBelow"
                    class="w-full max-w-3xl transition-all duration-700"
                    :class="[
                        showOtherPlaces
                            ? 'opacity-100 translate-y-0'
                            : 'opacity-0 translate-y-8 pointer-events-none'
                    ]"
                >
                    <div
                        class="rounded-2xl p-3.5 sm:p-5"
                        style="background: var(--surface-2); border: 1px solid var(--border)"
                    >
                        <div class="mb-2 text-xs font-semibold tracking-wider uppercase" style="color: var(--text-faint)">
                            4位以下ランキング
                        </div>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 md:grid-cols-3">
                            <div
                                v-for="team in otherPlaces"
                                :key="team.teamName"
                                class="flex items-center justify-between rounded-xl px-3 py-2 text-sm transition-colors"
                                style="background: var(--surface-3)"
                            >
                                <div class="flex items-center gap-2 truncate">
                                    <span
                                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md text-xs font-bold"
                                        style="background: var(--overlay); color: var(--text-muted)"
                                    >
                                        {{ team.rank }}
                                    </span>
                                    <span class="truncate font-medium" style="color: var(--text)">
                                        {{ team.teamName }}
                                    </span>
                                </div>
                                <span
                                    class="shrink-0 text-xs font-semibold"
                                    style="color: var(--indigo-light); font-family: DM Mono, monospace"
                                >
                                    {{ team.total.toLocaleString() }} pt
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ボトム司会・操作コントローラーバー -->
        <div class="relative z-30 mx-auto flex w-full max-w-xl flex-wrap items-center justify-center gap-2 rounded-2xl p-2.5 sm:gap-3"
             style="background: var(--surface-2); border: 1px solid var(--border)">
            <!-- 「次へ」ボタン (Step 0〜4) -->
            <button
                v-if="step < 5"
                type="button"
                class="flex items-center gap-1.5 rounded-xl px-5 py-2 text-sm font-bold transition-all hover:scale-105 active:scale-95"
                style="background: var(--indigo); color: var(--on-accent)"
                @click="nextStep"
            >
                <span>{{ step === 0 ? '表彰開始' : (step === 4 ? '優勝発表へ！' : '次の順位へ') }}</span>
                <span>▶</span>
            </button>

            <!-- 自動再生トグル -->
            <button
                v-if="step < 5"
                type="button"
                class="rounded-xl px-3 py-2 text-xs font-medium transition-colors"
                :style="{
                    background: isAutoPlay ? 'rgba(99, 102, 241, 0.2)' : 'transparent',
                    color: isAutoPlay ? 'var(--indigo-light)' : 'var(--text-muted)',
                    border: '1px solid ' + (isAutoPlay ? 'var(--indigo)' : 'var(--border)'),
                }"
                @click="toggleAutoPlay"
            >
                {{ isAutoPlay ? '⏸ 一時停止' : '▶ 自動進行' }}
            </button>

            <!-- スキップ (一括表示) -->
            <button
                v-if="step < 5"
                type="button"
                class="rounded-xl px-3 py-2 text-xs font-medium transition-colors hover:text-white"
                style="color: var(--text-faint)"
                @click="skipToEnd"
            >
                すべて表示
            </button>

            <!-- もう一度再生 (Step 5) -->
            <button
                v-if="step === 5"
                type="button"
                class="flex items-center gap-1.5 rounded-xl px-5 py-2 text-sm font-semibold transition-all hover:scale-105"
                style="background: var(--surface-3); color: var(--text); border: 1px solid var(--border)"
                @click="restart"
            >
                <span>↺ もう一度再生</span>
            </button>

            <!-- 閉じるボタン -->
            <button
                type="button"
                class="rounded-xl px-4 py-2 text-xs font-medium transition-colors hover:bg-white/10"
                style="color: var(--text-muted)"
                @click="emit('close')"
            >
                閉じる
            </button>
        </div>
    </div>
    </Teleport>
</template>

<style scoped>
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(12px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in {
    animation: fadeIn 0.4s ease-out forwards;
}

@keyframes bounceShort {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-8px);
    }
}

.animate-bounce-short {
    animation: bounceShort 1.5s ease-in-out infinite;
}

.ceremony-gold-name {
    color: #fbbf24;
}

.ceremony-gold-pts {
    color: #fde68a;
}

.ceremony-silver-name {
    color: var(--text);
}

.ceremony-silver-pts {
    color: #cbd5e1;
}

.ceremony-silver-num {
    color: #cbd5e1;
}

.ceremony-silver-label {
    color: #94a3b8;
}

.ceremony-bronze-pts {
    color: #f59e0b;
}

.ceremony-bronze-num {
    color: #f59e0b;
}
</style>

<style>
html[data-theme='bright'] .ceremony-gold-name {
    color: #d97706 !important;
}

html[data-theme='bright'] .ceremony-gold-pts {
    color: #b45309 !important;
}

html[data-theme='bright'] .ceremony-silver-name {
    color: #0f172a !important;
}

html[data-theme='bright'] .ceremony-silver-pts {
    color: #0f172a !important;
    font-weight: 700 !important;
}

html[data-theme='bright'] .ceremony-silver-num {
    color: #0f172a !important;
    opacity: 1 !important;
}

html[data-theme='bright'] .ceremony-silver-label {
    color: #1e293b !important;
    font-weight: 700 !important;
}

html[data-theme='bright'] .ceremony-silver-podium {
    background: linear-gradient(180deg, rgba(148, 163, 184, 0.55) 0%, rgba(100, 116, 139, 0.28) 100%) !important;
    border: 1.5px solid rgba(71, 85, 105, 0.5) !important;
    border-bottom: none !important;
}

html[data-theme='bright'] .ceremony-bronze-pts {
    color: #b45309 !important;
}

html[data-theme='bright'] .ceremony-bronze-num {
    color: #b45309 !important;
}
</style>
