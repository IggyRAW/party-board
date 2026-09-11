<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router, usePoll } from '@inertiajs/vue3';
import FullscreenRoulette from '../Components/FullscreenRoulette.vue';
import RouletteTab from '../Components/RouletteTab.vue';
import ScoreTab from '../Components/ScoreTab.vue';

const THEME_KEY = 'party-board-theme';
const MOBILE_MEDIA = '(max-width: 767px)';
const BOARD_POLL_MS = 2000;
const BOARD_POLL_PROPS = [
    'games',
    'teams',
    'scoreEntries',
    'prizes',
    'wonPrizeIds',
    'finalePrizeLimit',
    'participants',
    'winHistory',
];

const props = defineProps({
    tab: { type: String, default: 'score' },
    selectedGameId: { type: Number, default: null },
    games: { type: Array, required: true },
    teams: { type: Array, required: true },
    scoreEntries: { type: Array, required: true },
    prizes: { type: Array, required: true },
    wonPrizeIds: { type: Array, required: true },
    finalePrizeLimit: { type: Number, required: true },
    participants: { type: Array, required: true },
    winHistory: { type: Array, required: true },
});

const theme = ref(document.documentElement.dataset.theme === 'bright' ? 'bright' : 'dark');
const isMobile = ref(window.matchMedia(MOBILE_MEDIA).matches);
const currentTab = ref(props.tab === 'roulette' && !isMobile.value ? 'roulette' : 'score');
const selectedGameId = ref(props.selectedGameId);
const selectedTeamId = ref(null);
const spinning = ref(false);
const rotation = ref(0);
const winner = ref(null);
const rouletteFullscreen = ref(false);

let spinCurrent = 0;
let rafId = 0;
let mobileMedia = null;

const { start: startPolling, stop: stopPolling } = usePoll(
    BOARD_POLL_MS,
    {
        only: BOARD_POLL_PROPS,
    },
    {
        mode: 'rest',
    },
);

watch(spinning, (isSpinning) => {
    if (isSpinning) {
        stopPolling();
        return;
    }

    startPolling();
});

watch(
    () => props.selectedGameId,
    (value) => {
        if (value) {
            selectedGameId.value = value;
        }
    },
);

watch(
    () => props.games,
    (games) => {
        if (selectedGameId.value && !games.some((game) => game.id === selectedGameId.value)) {
            selectedGameId.value = games[0]?.id ?? null;
        }
    },
);

watch(
    () => props.teams,
    (teams) => {
        if (selectedTeamId.value && !teams.some((team) => team.id === selectedTeamId.value)) {
            selectedTeamId.value = null;
        }
    },
);

watch(
    () => props.winHistory,
    (history) => {
        if (!spinning.value && history.length > 0) {
            winner.value = history[history.length - 1].winner;
        }
        if (history.length === 0) {
            winner.value = null;
        }
    },
    { deep: true },
);

function isPrizeWon(prize) {
    return prize.isWon || props.wonPrizeIds.includes(prize.id);
}

const availablePrizes = computed(() => props.prizes.filter((prize) => !isPrizeWon(prize)));

const drawablePrizes = computed(() => {
    const regular = availablePrizes.value.filter((prize) => !prize.isFinale);

    return regular.length > 0 ? regular : availablePrizes.value;
});

const canSpin = computed(
    () => !spinning.value && props.participants.length > 0 && drawablePrizes.value.length > 0,
);

const lastWin = computed(() =>
    props.winHistory.length > 0 ? props.winHistory[props.winHistory.length - 1] : null,
);

function applyTheme(next) {
    theme.value = next;
    document.documentElement.dataset.theme = next;
    localStorage.setItem(THEME_KEY, next);
}

function toggleTheme() {
    applyTheme(theme.value === 'bright' ? 'dark' : 'bright');
}

function setTab(tab) {
    const next = isMobile.value ? 'score' : tab;
    currentTab.value = next;
    router.get('/', { tab: next, game: selectedGameId.value }, { preserveState: true, preserveScroll: true, replace: true });
}

function applyMobileLayout(matches) {
    isMobile.value = matches;

    if (!matches) {
        return;
    }

    rouletteFullscreen.value = false;

    if (currentTab.value === 'roulette' || props.tab === 'roulette') {
        setTab('score');
    }
}

function onMobileMediaChange(event) {
    applyMobileLayout(event.matches);
}

function rotationToPrizeIndex(index, count, currentRotation) {
    const slice = 360 / count;
    const jitter = (Math.random() - 0.5) * slice * 0.7;
    const adjusted = (index + 0.5) * slice + jitter;
    const endRot = (630 - adjusted + 360) % 360;
    const current = ((currentRotation % 360) + 360) % 360;
    let delta = (endRot - current + 360) % 360;

    if (delta < 40) {
        delta += 360;
    }

    return 1800 + Math.floor(Math.random() * 2) * 360 + delta;
}

function startSpin() {
    const prizes = availablePrizes.value;
    const pool = drawablePrizes.value;
    if (spinning.value || props.participants.length === 0 || pool.length === 0 || prizes.length === 0) {
        return;
    }

    const capturedPrizes = [...prizes];
    const wonPrize = pool[Math.floor(Math.random() * pool.length)];
    const prizeIdx = capturedPrizes.findIndex((prize) => prize.id === wonPrize.id);

    winner.value = null;
    spinning.value = true;
    const totalDeg = rotationToPrizeIndex(
        prizeIdx >= 0 ? prizeIdx : 0,
        capturedPrizes.length,
        spinCurrent,
    );
    const duration = 4000;
    const startedAt = performance.now();
    const startRot = spinCurrent;

    const animate = (time) => {
        const elapsed = time - startedAt;
        const progress = Math.min(elapsed / duration, 1);
        const ease = 1 - (1 - progress) ** 4;
        spinCurrent = (startRot + totalDeg * ease) % 360;
        rotation.value = spinCurrent;

        if (progress < 1) {
            rafId = requestAnimationFrame(animate);
            return;
        }

        spinning.value = false;

        router.post(
            '/roulette/spin',
            { prize_id: wonPrize.id },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    const history = page.props.winHistory ?? [];
                    winner.value = history.length ? history[history.length - 1].winner : null;
                },
            },
        );
    };

    rafId = requestAnimationFrame(animate);
}

onMounted(() => {
    mobileMedia = window.matchMedia(MOBILE_MEDIA);
    applyMobileLayout(mobileMedia.matches);
    mobileMedia.addEventListener('change', onMobileMediaChange);
});

onBeforeUnmount(() => {
    cancelAnimationFrame(rafId);
    mobileMedia?.removeEventListener('change', onMobileMediaChange);
});
</script>

<template>
    <div class="min-h-full w-full" style="background: var(--surface); color: var(--text); font-family: Inter, sans-serif">
        <FullscreenRoulette
            v-if="!isMobile && rouletteFullscreen"
            :available-prizes="availablePrizes"
            :participants="participants"
            :spinning="spinning"
            :rotation="rotation"
            :winner="winner"
            :last-win="lastWin"
            @spin="startSpin"
            @close="rouletteFullscreen = false"
        />

        <header style="border-bottom: 1px solid var(--border); background: var(--surface-2)">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6">
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-lg text-base"
                        style="background: var(--indigo)"
                    >
                        🎉
                    </div>
                    <span style="font-family: Outfit, sans-serif; font-weight: 700; font-size: 18px">Party Board</span>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="rounded-xl px-3 py-2 text-xs font-medium transition-all duration-200 sm:text-sm"
                        title="ログアウト"
                        :style="{
                            fontFamily: 'Outfit, sans-serif',
                            background: 'var(--surface-3)',
                            color: 'var(--text-muted)',
                            border: '1px solid var(--border)',
                        }"
                        @click="router.post('/logout')"
                    >
                        ログアウト
                    </button>
                    <button
                        type="button"
                        class="rounded-xl px-3 py-2 text-xs font-medium transition-all duration-200 sm:text-sm"
                        :title="theme === 'bright' ? '暗転（会場向け）' : '明転（プロジェクター向け）'"
                        :style="{
                            fontFamily: 'Outfit, sans-serif',
                            background: 'var(--surface-3)',
                            color: 'var(--text)',
                            border: '1px solid var(--border)',
                        }"
                        @click="toggleTheme"
                    >
                        {{ theme === 'bright' ? '🌙 暗転' : '☀️ 明転' }}
                    </button>
                    <div class="hidden gap-1 rounded-xl p-1 md:flex" style="background: var(--surface-3)">
                        <button
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-xs font-medium transition-all duration-200 sm:px-4 sm:text-sm"
                            :style="{
                                fontFamily: 'Outfit, sans-serif',
                                background: currentTab === 'score' ? 'var(--indigo)' : 'transparent',
                                color: currentTab === 'score' ? 'var(--on-accent)' : 'var(--text-muted)',
                            }"
                            @click="setTab('score')"
                        >
                            📊 スコア集計
                        </button>
                        <button
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-xs font-medium transition-all duration-200 sm:px-4 sm:text-sm"
                            :style="{
                                fontFamily: 'Outfit, sans-serif',
                                background: currentTab === 'roulette' ? 'var(--indigo)' : 'transparent',
                                color: currentTab === 'roulette' ? 'var(--on-accent)' : 'var(--text-muted)',
                            }"
                            @click="setTab('roulette')"
                        >
                            🎡 抽選ルーレット
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
            <ScoreTab
                v-if="isMobile || currentTab === 'score'"
                :games="games"
                :teams="teams"
                :score-entries="scoreEntries"
                :selected-game-id="selectedGameId"
                :selected-team-id="selectedTeamId"
                @select-game="selectedGameId = $event"
                @select-team="selectedTeamId = $event"
            />
            <RouletteTab
                v-else
                :prizes="prizes"
                :won-prize-ids="wonPrizeIds"
                :finale-prize-limit="finalePrizeLimit"
                :available-prizes="availablePrizes"
                :participants="participants"
                :spinning="spinning"
                :rotation="rotation"
                :can-spin="canSpin"
                :winner="winner"
                :win-history="winHistory"
                @spin="startSpin"
                @fullscreen="rouletteFullscreen = true"
            />
        </main>
    </div>
</template>
