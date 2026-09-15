<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Card from './Card.vue';
import EmptyState from './EmptyState.vue';
import RouletteWheel from './RouletteWheel.vue';

const props = defineProps({
    prizes: { type: Array, required: true },
    wonPrizeIds: { type: Array, required: true },
    finalePrizeLimit: { type: Number, required: true },
    finaleHoldUntilRemaining: { type: Number, required: true },
    availablePrizes: { type: Array, required: true },
    participants: { type: Array, required: true },
    spinning: { type: Boolean, required: true },
    rotation: { type: Number, required: true },
    canSpin: { type: Boolean, required: true },
    winner: { type: String, default: null },
    winHistory: { type: Array, required: true },
});

const emit = defineEmits(['spin', 'fullscreen']);

const prizeInput = ref('');
const participantInput = ref('');
const showBulkPrize = ref(false);
const bulkPrizeText = ref('');
const showBulkParticipant = ref(false);
const bulkParticipantText = ref('');

const lastWin = computed(() =>
    props.winHistory.length > 0 ? props.winHistory[props.winHistory.length - 1] : null,
);

const finaleLimitInput = ref(props.finalePrizeLimit);

watch(
    () => props.finalePrizeLimit,
    (value) => {
        finaleLimitInput.value = value;
    },
);

const showOperatorSettings = ref(false);

const markedFinaleCount = computed(() => props.prizes.filter((prize) => prize.isFinale).length);

function addPrize() {
    if (!prizeInput.value.trim()) {
        return;
    }

    router.post(
        '/prizes',
        { name: prizeInput.value.trim() },
        {
            preserveScroll: true,
            onSuccess: () => {
                prizeInput.value = '';
            },
        },
    );
}

function addBulkPrizes() {
    if (!bulkPrizeText.value.trim()) {
        return;
    }

    router.post(
        '/prizes/bulk',
        { names: bulkPrizeText.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                bulkPrizeText.value = '';
                showBulkPrize.value = false;
            },
        },
    );
}

function addParticipant() {
    if (!participantInput.value.trim()) {
        return;
    }

    router.post(
        '/participants',
        { name: participantInput.value.trim() },
        {
            preserveScroll: true,
            onSuccess: () => {
                participantInput.value = '';
            },
        },
    );
}

function addBulkParticipants() {
    if (!bulkParticipantText.value.trim()) {
        return;
    }

    router.post(
        '/participants/bulk',
        { names: bulkParticipantText.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                bulkParticipantText.value = '';
                showBulkParticipant.value = false;
            },
        },
    );
}

function resetRoulette() {
    router.post('/roulette/reset', {}, { preserveScroll: true });
}

function exportRoulette() {
    window.location.href = '/export/roulette';
}

function isWon(prize) {
    return prize.isWon || props.wonPrizeIds.includes(prize.id);
}

function saveFinaleLimit() {
    const limit = Number(finaleLimitInput.value);

    if (!Number.isInteger(limit) || limit < 0 || limit > 100) {
        finaleLimitInput.value = props.finalePrizeLimit;
        return;
    }

    if (limit === props.finalePrizeLimit) {
        return;
    }

    router.patch('/roulette/settings', { finale_prize_limit: limit }, { preserveScroll: true });
}

function toggleFinale(prize) {
    if (isWon(prize)) {
        return;
    }

    const next = !prize.isFinale;

    if (next && markedFinaleCount.value >= props.finalePrizeLimit) {
        return;
    }

    router.patch(
        `/prizes/${prize.id}/finale`,
        { is_finale: next },
        { preserveScroll: true },
    );
}
</script>

<template>
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div class="flex flex-col gap-5">
            <Card title="ルーレット">
                <template #action>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            title="リセット"
                            class="rounded-lg px-2.5 py-1 text-xs transition-colors"
                            style="background: var(--overlay); color: var(--text-muted); border: 1px solid var(--overlay-border)"
                            @click="resetRoulette"
                        >
                            ↺ リセット
                        </button>
                        <button
                            type="button"
                            title="全画面表示"
                            class="flex h-7 w-7 items-center justify-center rounded-lg text-sm transition-colors"
                            style="background: var(--overlay); color: var(--text-muted); border: 1px solid var(--overlay-border)"
                            @click="emit('fullscreen')"
                        >
                            ⛶
                        </button>
                    </div>
                </template>

                <div class="flex flex-col items-center gap-4">
                    <div class="text-center text-xs" style="color: var(--text-faint)">
                        {{ availablePrizes.length > 0 ? `景品 ${availablePrizes.length} 件がホイールに表示中` : '景品を追加してください' }}
                    </div>
                    <RouletteWheel :items="availablePrizes.map((p) => p.name)" :rotation="rotation" />
                    <button
                        type="button"
                        class="w-full rounded-xl py-3 text-base font-bold transition-all duration-200"
                        :disabled="!canSpin"
                        :style="{
                            fontFamily: 'Outfit, sans-serif',
                            background: canSpin ? 'var(--indigo)' : 'rgba(99,102,241,0.3)',
                            color: canSpin ? 'var(--on-accent)' : 'var(--text-faint)',
                            cursor: canSpin ? 'pointer' : 'not-allowed',
                        }"
                        @click="emit('spin')"
                    >
                        {{ spinning ? '抽選中…' : '🎰 スタート' }}
                    </button>
                    <div
                        v-if="winner && !spinning && lastWin"
                        class="w-full rounded-xl py-4 text-center"
                        style="background: rgba(99,102,241,0.15); border: 1px solid rgba(99,102,241,0.4)"
                    >
                        <div class="mb-1 text-xs" style="color: var(--indigo-light)">当選者</div>
                        <div class="text-2xl font-bold" style="font-family: Outfit, sans-serif">🎊 {{ winner }}</div>
                        <div class="mt-1 text-sm" style="color: var(--text-subtle)">景品: {{ lastWin.prize }}</div>
                    </div>
                </div>
            </Card>

            <Card title="当選履歴">
                <template #action>
                    <button
                        type="button"
                        class="rounded-lg px-3 py-1 text-xs"
                        style="background: rgba(99,102,241,0.15); color: var(--indigo-light); border: 1px solid rgba(99,102,241,0.3)"
                        @click="exportRoulette"
                    >
                        ↓ エクスポート
                    </button>
                </template>
                <div class="flex flex-col gap-2 overflow-y-auto" style="max-height: 180px">
                    <EmptyState v-if="winHistory.length === 0" text="まだ当選者はいません" />
                    <div
                        v-for="(record, index) in [...winHistory].reverse()"
                        :key="record.id ?? index"
                        class="flex items-center justify-between rounded-xl px-3 py-2.5"
                        style="background: var(--surface-3)"
                    >
                        <div>
                            <div class="text-sm font-semibold">{{ record.winner }}</div>
                            <div class="mt-0.5 text-xs" style="color: var(--indigo-light)">{{ record.prize }}</div>
                        </div>
                        <span class="text-xs" style="color: var(--text-faint); font-family: DM Mono, monospace">
                            {{ record.timestamp }}
                        </span>
                    </div>
                </div>
            </Card>
        </div>

        <div class="flex flex-col gap-5">
            <Card title="景品一覧">
                <template #action>
                    <button
                        type="button"
                        title="設定"
                        class="flex h-7 w-7 items-center justify-center rounded-lg text-xs transition-opacity"
                        :class="showOperatorSettings ? 'opacity-100' : 'opacity-0 hover:opacity-50 focus-visible:opacity-50'"
                        :style="{
                            background: showOperatorSettings ? 'rgba(99,102,241,0.3)' : 'transparent',
                            color: showOperatorSettings ? 'var(--indigo-light)' : 'var(--text-faint)',
                            border: showOperatorSettings ? '1px solid rgba(99,102,241,0.3)' : '1px solid transparent',
                        }"
                        @click="showOperatorSettings = !showOperatorSettings"
                    >
                        ⚙
                    </button>
                </template>
                <div
                    v-if="showOperatorSettings"
                    class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-xl px-3 py-2"
                    style="background: var(--surface-3); border: 1px solid var(--border)"
                >
                    <p class="text-xs" style="color: var(--text-muted)">
                        残り{{ finaleHoldUntilRemaining }}人まで当たらない景品（{{ markedFinaleCount }}/{{ finalePrizeLimit }}）
                    </p>
                    <label class="flex items-center gap-1.5 text-xs" style="color: var(--text-muted)">
                        件数
                        <input
                            v-model.number="finaleLimitInput"
                            type="number"
                            min="0"
                            max="100"
                            class="w-14 rounded-lg px-2 py-1 text-center text-sm outline-none"
                            style="background: var(--surface-2); color: var(--text); border: 1px solid var(--border)"
                            @change="saveFinaleLimit"
                            @keydown.enter="saveFinaleLimit"
                        >
                    </label>
                </div>
                <div class="mb-3 flex items-center justify-between">
                    <div class="mr-2 flex flex-1 gap-2">
                        <input
                            v-model="prizeInput"
                            placeholder="景品名を入力"
                            class="flex-1 rounded-lg px-3 py-2 text-sm outline-none"
                            style="background: var(--surface-3); color: var(--text); border: 1px solid var(--border)"
                            @keydown.enter="addPrize"
                        >
                        <button
                            type="button"
                            class="shrink-0 whitespace-nowrap rounded-lg px-4 py-2 text-sm font-medium"
                            style="background: var(--indigo); color: var(--on-accent)"
                            @click="addPrize"
                        >
                            追加
                        </button>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg px-2.5 py-2 text-xs whitespace-nowrap transition-colors"
                        :style="{
                            background: showBulkPrize ? 'rgba(99,102,241,0.3)' : 'var(--overlay)',
                            color: showBulkPrize ? 'var(--indigo-light)' : 'var(--text-muted)',
                            border: '1px solid var(--overlay-border)',
                        }"
                        @click="showBulkPrize = !showBulkPrize"
                    >
                        一括入力
                    </button>
                </div>
                <div v-if="showBulkPrize" class="mb-3 flex flex-col gap-2">
                    <textarea
                        v-model="bulkPrizeText"
                        rows="4"
                        placeholder="景品名を1行ずつ入力&#10;例:&#10;Amazonギフト券&#10;QUOカード"
                        class="w-full resize-none rounded-lg px-3 py-2 text-sm outline-none"
                        style="background: var(--surface-3); color: var(--text); border: 1px solid var(--border)"
                    />
                    <button
                        type="button"
                        class="self-end rounded-lg px-4 py-1.5 text-sm font-medium"
                        style="background: var(--indigo); color: var(--on-accent)"
                        @click="addBulkPrizes"
                    >
                        確定
                    </button>
                </div>
                <div class="flex flex-col gap-2 overflow-y-auto" style="max-height: 200px">
                    <div
                        v-for="prize in prizes"
                        :key="prize.id"
                        class="flex items-center justify-between rounded-lg px-3 py-2.5"
                        :style="{
                            background: isWon(prize) ? 'var(--won-fill)' : 'var(--surface-3)',
                            opacity: isWon(prize) ? 0.5 : 1,
                        }"
                    >
                        <div class="flex min-w-0 items-center gap-2">
                            <button
                                v-if="showOperatorSettings"
                                type="button"
                                class="shrink-0 text-sm transition-opacity"
                                :disabled="isWon(prize) || (!prize.isFinale && markedFinaleCount >= finalePrizeLimit)"
                                :title="prize.isFinale ? '上位景品の指定を解除' : '残り人数が減るまで当たらない上位景品に指定'"
                                :style="{
                                    color: prize.isFinale ? '#f59e0b' : 'var(--text-faint)',
                                    opacity: isWon(prize) ? 0.35 : 1,
                                    cursor: isWon(prize) ? 'not-allowed' : 'pointer',
                                }"
                                @click="toggleFinale(prize)"
                            >
                                {{ prize.isFinale ? '★' : '☆' }}
                            </button>
                            <span class="truncate text-sm" :style="{ color: isWon(prize) ? 'var(--text-faint)' : 'var(--text)' }">
                                {{ prize.name }}
                            </span>
                            <span
                                v-if="isWon(prize)"
                                class="shrink-0 rounded px-1.5 py-0.5 text-xs"
                                style="background: rgba(245,158,11,0.2); color: #f59e0b"
                            >
                                当選済み
                            </span>
                            <span
                                v-else-if="showOperatorSettings && prize.isFinale"
                                class="shrink-0 rounded px-1.5 py-0.5 text-xs"
                                style="background: rgba(245,158,11,0.15); color: #f59e0b"
                            >
                                上位景品
                            </span>
                        </div>
                        <button
                            v-if="!isWon(prize)"
                            type="button"
                            class="text-xs opacity-40 transition-opacity hover:opacity-100"
                            style="color: var(--danger)"
                            @click="router.delete(`/prizes/${prize.id}`, { preserveScroll: true })"
                        >
                            ✕
                        </button>
                    </div>
                </div>
            </Card>

            <Card :title="`参加者 (${participants.length}名)`">
                <div class="mb-3 flex items-center justify-between">
                    <div class="mr-2 flex flex-1 gap-2">
                        <input
                            v-model="participantInput"
                            placeholder="参加者名を入力"
                            class="flex-1 rounded-lg px-3 py-2 text-sm outline-none"
                            style="background: var(--surface-3); color: var(--text); border: 1px solid var(--border)"
                            @keydown.enter="addParticipant"
                        >
                        <button
                            type="button"
                            class="shrink-0 whitespace-nowrap rounded-lg px-4 py-2 text-sm font-medium"
                            style="background: var(--indigo); color: var(--on-accent)"
                            @click="addParticipant"
                        >
                            追加
                        </button>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg px-2.5 py-2 text-xs whitespace-nowrap transition-colors"
                        :style="{
                            background: showBulkParticipant ? 'rgba(99,102,241,0.3)' : 'var(--overlay)',
                            color: showBulkParticipant ? 'var(--indigo-light)' : 'var(--text-muted)',
                            border: '1px solid var(--overlay-border)',
                        }"
                        @click="showBulkParticipant = !showBulkParticipant"
                    >
                        一括入力
                    </button>
                </div>
                <div v-if="showBulkParticipant" class="mb-3 flex flex-col gap-2">
                    <textarea
                        v-model="bulkParticipantText"
                        rows="4"
                        placeholder="参加者名を1行ずつ入力&#10;例:&#10;田中 太郎&#10;鈴木 花子"
                        class="w-full resize-none rounded-lg px-3 py-2 text-sm outline-none"
                        style="background: var(--surface-3); color: var(--text); border: 1px solid var(--border)"
                    />
                    <button
                        type="button"
                        class="self-end rounded-lg px-4 py-1.5 text-sm font-medium"
                        style="background: var(--indigo); color: var(--on-accent)"
                        @click="addBulkParticipants"
                    >
                        確定
                    </button>
                </div>
                <div class="flex flex-col gap-2 overflow-y-auto" style="max-height: 360px">
                    <div v-if="participants.length === 0">
                        <EmptyState text="全員当選しました 🎉" />
                    </div>
                    <div
                        v-for="(participant, index) in participants"
                        :key="participant.id"
                        class="flex items-center justify-between rounded-xl px-3 py-2.5"
                        style="background: var(--surface-3)"
                    >
                        <div class="flex items-center gap-2">
                            <span class="w-5 text-center text-xs" style="color: var(--text-faint); font-family: DM Mono, monospace">
                                {{ String(index + 1).padStart(2, '0') }}
                            </span>
                            <span class="truncate text-sm font-medium">{{ participant.name }}</span>
                        </div>
                        <button
                            type="button"
                            class="ml-1 text-xs opacity-40 transition-opacity hover:opacity-100"
                            style="color: var(--danger)"
                            @click="router.delete(`/participants/${participant.id}`, { preserveScroll: true })"
                        >
                            ✕
                        </button>
                    </div>
                </div>
            </Card>
        </div>
    </div>
</template>
