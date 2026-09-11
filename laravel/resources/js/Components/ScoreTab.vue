<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Card from './Card.vue';
import EmptyState from './EmptyState.vue';

const props = defineProps({
    games: { type: Array, required: true },
    teams: { type: Array, required: true },
    scoreEntries: { type: Array, required: true },
    selectedGameId: { type: Number, default: null },
    selectedTeamId: { type: Number, default: null },
});

const emit = defineEmits(['select-game', 'select-team']);

const gameInput = ref('');
const teamInput = ref('');
const scoreInput = ref('');
const scoreLabelInput = ref('');
const filterGame = ref('all');
const editingTeamId = ref(null);
const editingTeamName = ref('');
const editingEntryId = ref(null);
const editingEntryScore = ref('');
const saveFlashId = ref(null);
const editingLabelId = ref(null);
const editingLabelText = ref('');
const rankEmoji = ['🥇', '🥈', '🥉'];

const canAddScore = computed(() => scoreInput.value !== '' && !Number.isNaN(Number(scoreInput.value)));

// インライン編集の入力欄は開いた直後に全選択しておき、タップ1回で上書き入力できるようにする。
// v-model の mounted が値を再代入して選択を解除するため、テンプレートでは必ず v-model より後に置く
const vFocusSelect = {
    mounted(el) {
        el.focus();
        el.setSelectionRange(0, el.value.length);
    },
};

// 日本語IMEの変換確定Enterで確定処理が走らないようにする
function onEnter(event, action) {
    if (event.isComposing) {
        return;
    }

    action();
}

// マイナスは先頭の1文字だけ許容する
function sanitizeScore(value) {
    const isNegative = value.startsWith('-');
    const digits = value.replace(/[^0-9]/g, '');

    if (digits === '') {
        return isNegative ? '-' : '';
    }

    return isNegative ? `-${digits}` : digits;
}

const selectedGame = () => props.games.find((game) => game.id === props.selectedGameId) ?? null;
const selectedTeam = () => props.teams.find((team) => team.id === props.selectedTeamId) ?? null;

function teamEntriesForSelected() {
    return props.scoreEntries.filter(
        (entry) => entry.teamId === props.selectedTeamId && entry.gameId === props.selectedGameId,
    );
}

function teamTotal(teamId, gameId = props.selectedGameId) {
    return props.scoreEntries
        .filter((entry) => entry.teamId === teamId && (gameId === null || entry.gameId === gameId))
        .reduce((sum, entry) => sum + entry.score, 0);
}

function rankingEntries() {
    return props.teams
        .map((team) => {
            const game = filterGame.value === 'all'
                ? null
                : props.games.find((item) => item.id === filterGame.value);

            return {
                teamName: team.name,
                gameName: game?.name ?? '',
                total: teamTotal(team.id, filterGame.value === 'all' ? null : filterGame.value),
            };
        })
        .sort((a, b) => b.total - a.total)
        .map((row, index) => ({ ...row, rank: index + 1 }));
}

function addGame() {
    if (!gameInput.value.trim()) {
        return;
    }

    router.post(
        '/games',
        { name: gameInput.value.trim() },
        {
            preserveScroll: true,
            onSuccess: () => {
                gameInput.value = '';
            },
        },
    );
}

function deleteGame(game, event) {
    event.stopPropagation();
    router.delete(`/games/${game.id}`, { preserveScroll: true });
}

function addTeam() {
    const name = teamInput.value.trim();

    if (!name || props.teams.some((team) => team.name === name)) {
        return;
    }

    router.post(
        '/teams',
        { name },
        {
            preserveScroll: true,
            onSuccess: () => {
                teamInput.value = '';
            },
        },
    );
}

function commitTeamName(id) {
    if (editingTeamName.value.trim()) {
        router.patch(`/teams/${id}`, { name: editingTeamName.value.trim() }, { preserveScroll: true });
    }
    editingTeamId.value = null;
}

function deleteTeam(team, event) {
    event.stopPropagation();
    router.delete(`/teams/${team.id}`, { preserveScroll: true });
    if (props.selectedTeamId === team.id) {
        emit('select-team', null);
    }
}

function addScore() {
    if (!canAddScore.value || props.selectedTeamId === null || props.selectedGameId === null) {
        return;
    }

    router.post(
        '/score-entries',
        {
            team_id: props.selectedTeamId,
            game_id: props.selectedGameId,
            score: Number(scoreInput.value),
            label: scoreLabelInput.value.trim() || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                scoreInput.value = '';
                scoreLabelInput.value = '';
            },
        },
    );
}

function commitEntryScore(id) {
    const value = Number(editingEntryScore.value);
    if (editingEntryScore.value !== '' && !Number.isNaN(value)) {
        router.patch(
            `/score-entries/${id}`,
            { score: value },
            {
                preserveScroll: true,
                onSuccess: () => {
                    saveFlashId.value = id;
                    setTimeout(() => {
                        saveFlashId.value = null;
                    }, 1500);
                },
            },
        );
    }
    editingEntryId.value = null;
}

function startLabelEdit(entry) {
    editingLabelId.value = entry.id;
    editingLabelText.value = entry.label ?? '';
}

function startScoreEdit(entry) {
    editingEntryId.value = entry.id;
    editingEntryScore.value = String(entry.score);
}

function commitLabel(id) {
    if (editingLabelText.value.trim()) {
        router.patch(`/score-entries/${id}`, { label: editingLabelText.value.trim() }, { preserveScroll: true });
    }
    editingLabelId.value = null;
}

function deleteEntry(id) {
    router.delete(`/score-entries/${id}`, { preserveScroll: true });
}

function exportScores() {
    window.location.href = `/export/scores?filter=${filterGame.value}`;
}

function onSelectGame(game) {
    emit('select-game', game.id);
}

function onSelectTeam(team) {
    if (editingTeamId.value === team.id) {
        return;
    }
    emit('select-team', props.selectedTeamId === team.id ? null : team.id);
}
</script>

<template>
    <div class="grid grid-cols-1 gap-6 md:grid-cols-[300px_1fr]">
        <div class="flex flex-col gap-5">
            <Card title="ゲーム">
                <div class="mb-3 flex min-w-0 gap-2">
                    <input
                        v-model="gameInput"
                        placeholder="ゲーム名を入力"
                        enterkeyhint="done"
                        class="min-h-11 min-w-0 flex-1 rounded-lg px-3 py-2 text-sm outline-none sm:min-h-0"
                        style="background: var(--surface-3); color: var(--text); border: 1px solid var(--border)"
                        @keydown.enter="onEnter($event, addGame)"
                    >
                    <button
                        type="button"
                        class="min-h-11 shrink-0 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium sm:min-h-0"
                        style="background: var(--indigo); color: var(--on-accent)"
                        @click="addGame"
                    >
                        追加
                    </button>
                </div>
                <div class="flex flex-col gap-1.5">
                    <div
                        v-for="game in games"
                        :key="game.id"
                        class="flex cursor-pointer items-center justify-between rounded-lg px-3 py-2 transition-colors"
                        :style="{
                            background: selectedGameId === game.id ? 'rgba(99,102,241,0.25)' : 'var(--surface-3)',
                            border: selectedGameId === game.id ? '1px solid rgba(99,102,241,0.5)' : '1px solid transparent',
                        }"
                        @click="onSelectGame(game)"
                    >
                        <span class="text-sm font-medium">{{ game.name }}</span>
                        <button
                            type="button"
                            title="ゲームを削除"
                            class="px-2 py-1 text-sm opacity-40 transition-opacity hover:opacity-100"
                            style="color: var(--danger)"
                            @click="deleteGame(game, $event)"
                        >
                            ✕
                        </button>
                    </div>
                </div>
            </Card>

            <Card title="チーム">
                <div class="mb-3 flex min-w-0 gap-2">
                    <input
                        v-model="teamInput"
                        placeholder="チーム名を入力"
                        enterkeyhint="done"
                        class="min-h-11 min-w-0 flex-1 rounded-lg px-3 py-2 text-sm outline-none sm:min-h-0"
                        style="background: var(--surface-3); color: var(--text); border: 1px solid var(--border)"
                        @keydown.enter="onEnter($event, addTeam)"
                    >
                    <button
                        type="button"
                        class="min-h-11 shrink-0 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium sm:min-h-0"
                        style="background: var(--indigo); color: var(--on-accent)"
                        @click="addTeam"
                    >
                        追加
                    </button>
                </div>
                <div class="flex flex-col gap-1.5">
                    <div
                        v-for="team in teams"
                        :key="team.id"
                        class="flex cursor-pointer items-center justify-between rounded-lg px-3 py-2 transition-colors"
                        :style="{
                            background: selectedTeamId === team.id ? 'rgba(99,102,241,0.25)' : 'var(--surface-3)',
                            border: selectedTeamId === team.id ? '1px solid rgba(99,102,241,0.5)' : '1px solid transparent',
                        }"
                        @click="onSelectTeam(team)"
                    >
                        <input
                            v-if="editingTeamId === team.id"
                            v-model="editingTeamName"
                            v-focus-select
                            enterkeyhint="done"
                            class="min-h-9 min-w-0 flex-1 rounded border-b bg-transparent px-1 py-1 text-sm outline-none sm:min-h-0"
                            style="color: var(--text); border-color: var(--indigo-light)"
                            @click.stop
                            @blur="commitTeamName(team.id)"
                            @keydown.enter="onEnter($event, () => commitTeamName(team.id))"
                            @keydown.escape="editingTeamId = null"
                        >
                        <span v-else class="flex-1 text-sm font-medium">{{ team.name }}</span>
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs" style="color: var(--indigo-light); font-family: DM Mono, monospace">
                                {{ teamTotal(team.id).toLocaleString() }} pt
                            </span>
                            <button
                                type="button"
                                title="チーム名を編集"
                                class="px-2 py-1 text-sm opacity-40 transition-opacity hover:opacity-100"
                                style="color: var(--text-subtle)"
                                @click.stop="editingTeamId = team.id; editingTeamName = team.name"
                            >
                                ✎
                            </button>
                            <button
                                type="button"
                                title="チームを削除"
                                class="px-2 py-1 text-sm opacity-40 transition-opacity hover:opacity-100"
                                style="color: var(--danger)"
                                @click="deleteTeam(team, $event)"
                            >
                                ✕
                            </button>
                        </div>
                    </div>
                </div>
            </Card>
        </div>

        <div class="flex flex-col gap-5">
            <Card v-if="selectedGame() && selectedTeam()" :title="`スコア — ${selectedGame()?.name ?? ''} / ${selectedTeam()?.name ?? ''}`">
                <div class="mb-3 flex min-w-0 flex-col gap-2 sm:flex-row sm:items-center">
                    <input
                        v-model="scoreLabelInput"
                        placeholder="問題ラベル（任意）"
                        enterkeyhint="done"
                        class="min-h-11 w-full min-w-0 rounded-lg px-3 py-2 text-sm outline-none sm:min-h-0 sm:flex-1"
                        style="background: var(--surface-3); color: var(--text); border: 1px solid var(--border)"
                        @keydown.enter="onEnter($event, addScore)"
                    >
                    <div class="flex min-w-0 gap-2">
                        <input
                            v-model="scoreInput"
                            type="text"
                            inputmode="numeric"
                            pattern="-?[0-9]*"
                            placeholder="スコア"
                            enterkeyhint="done"
                            class="min-h-11 w-28 shrink-0 rounded-lg px-3 py-2 text-center text-sm outline-none sm:min-h-0 sm:w-20 sm:text-left"
                            style="background: var(--surface-3); color: var(--text); border: 1px solid var(--border)"
                            @input="scoreInput = sanitizeScore(scoreInput)"
                            @keydown.enter="onEnter($event, addScore)"
                        >
                        <button
                            type="button"
                            class="min-h-11 flex-1 whitespace-nowrap rounded-lg px-4 py-2 text-sm font-semibold transition-all sm:min-h-0 sm:flex-none"
                            :disabled="!canAddScore"
                            :style="{
                                fontFamily: 'Outfit, sans-serif',
                                background: canAddScore ? 'var(--indigo)' : 'rgba(99,102,241,0.3)',
                                color: canAddScore ? 'var(--on-accent)' : 'var(--text-faint)',
                                cursor: canAddScore ? 'pointer' : 'not-allowed',
                            }"
                            @click="addScore"
                        >
                            + 追加
                        </button>
                    </div>
                </div>
                <div class="max-h-[50vh] overflow-y-auto sm:max-h-[260px]">
                    <div class="grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                        <div
                            v-for="entry in teamEntriesForSelected()"
                            :key="entry.id"
                            class="flex items-center justify-between rounded-lg px-3 py-2"
                            style="background: var(--surface-3)"
                        >
                            <input
                                v-if="editingLabelId === entry.id"
                                v-model="editingLabelText"
                                v-focus-select
                                enterkeyhint="done"
                                class="mr-2 min-h-9 min-w-0 flex-1 rounded border-b bg-transparent px-1 py-1 text-xs outline-none sm:min-h-0"
                                style="color: var(--indigo-light); border-color: var(--indigo-light)"
                                @blur="commitLabel(entry.id)"
                                @keydown.enter="onEnter($event, () => commitLabel(entry.id))"
                                @keydown.escape="editingLabelId = null"
                            >
                            <span
                                v-else
                                class="mr-2 min-w-0 cursor-pointer truncate rounded px-2 py-1 text-xs"
                                title="タップで編集"
                                style="background: rgba(99,102,241,0.2); color: var(--indigo-light); white-space: nowrap"
                                @click="startLabelEdit(entry)"
                            >
                                {{ entry.label || 'ラベル' }}
                            </span>
                            <span
                                v-if="saveFlashId === entry.id"
                                class="text-xs"
                                style="color: #4ade80; font-family: DM Mono, monospace"
                            >
                                保存済み
                            </span>
                            <input
                                v-else-if="editingEntryId === entry.id"
                                v-model="editingEntryScore"
                                v-focus-select
                                type="text"
                                inputmode="numeric"
                                pattern="-?[0-9]*"
                                enterkeyhint="done"
                                class="min-h-9 w-20 shrink-0 rounded border-b bg-transparent px-1 py-1 text-right text-sm outline-none sm:min-h-0 sm:w-16"
                                style="color: var(--indigo-light); border-color: var(--indigo-light); font-family: DM Mono, monospace"
                                @input="editingEntryScore = sanitizeScore(editingEntryScore)"
                                @blur="commitEntryScore(entry.id)"
                                @keydown.enter="onEnter($event, () => commitEntryScore(entry.id))"
                                @keydown.escape="editingEntryId = null"
                            >
                            <span
                                v-else
                                class="cursor-pointer rounded px-2 py-1 text-sm font-bold"
                                title="タップで編集"
                                :style="{
                                    fontFamily: 'DM Mono, monospace',
                                    color: entry.score === 0 ? 'var(--text-faint)' : 'var(--indigo-light)',
                                }"
                                @click="startScoreEdit(entry)"
                            >
                                {{ entry.score.toLocaleString() }}
                            </span>
                            <button
                                type="button"
                                title="削除"
                                class="ml-1 shrink-0 px-2 py-1 text-sm opacity-40 transition-opacity hover:opacity-100"
                                style="color: var(--danger)"
                                @click="deleteEntry(entry.id)"
                            >
                                ✕
                            </button>
                        </div>
                    </div>
                </div>
            </Card>

            <Card title="ランキング">
                <div class="mb-4 flex flex-wrap items-center gap-2">
                    <div class="flex flex-wrap gap-1">
                        <button
                            type="button"
                            class="rounded-full px-2.5 py-1 text-xs transition-colors"
                            :style="{
                                background: filterGame === 'all' ? 'var(--indigo)' : 'var(--surface-3)',
                                color: filterGame === 'all' ? 'var(--on-accent)' : 'var(--text-muted)',
                            }"
                            @click="filterGame = 'all'"
                        >
                            すべて
                        </button>
                        <button
                            v-for="game in games"
                            :key="game.id"
                            type="button"
                            class="rounded-full px-2.5 py-1 text-xs transition-colors"
                            :style="{
                                background: filterGame === game.id ? 'var(--indigo)' : 'var(--surface-3)',
                                color: filterGame === game.id ? 'var(--on-accent)' : 'var(--text-muted)',
                            }"
                            @click="filterGame = game.id"
                        >
                            {{ game.name }}
                        </button>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg px-3 py-1 text-xs"
                        style="background: rgba(99,102,241,0.15); color: var(--indigo-light); border: 1px solid rgba(99,102,241,0.3)"
                        @click="exportScores"
                    >
                        ↓ エクスポート
                    </button>
                </div>

                <EmptyState v-if="rankingEntries().length === 0" text="まだスコアがありません" />
                <div v-else class="flex flex-col gap-2">
                    <div
                        v-for="(row, index) in rankingEntries()"
                        :key="`${row.teamName}-${index}`"
                        class="flex items-center justify-between rounded-xl px-4 py-3"
                        style="background: var(--surface-3)"
                    >
                        <div class="flex items-center gap-3">
                            <span class="w-6 text-center text-lg">{{ row.rank <= 3 ? rankEmoji[row.rank - 1] : row.rank }}</span>
                            <div>
                                <span class="text-sm font-medium">{{ row.teamName }}</span>
                                <span
                                    v-if="filterGame !== 'all' && row.gameName"
                                    class="ml-2 text-xs"
                                    style="color: var(--text-faint); display: inline-block; max-width: 135px; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; vertical-align: bottom;"
                                >
                                    {{ row.gameName }}
                                </span>
                            </div>
                        </div>
                        <span class="text-sm font-bold" style="font-family: DM Mono, monospace; color: var(--indigo-light)">
                            {{ row.total.toLocaleString() }} pt
                        </span>
                    </div>
                </div>
            </Card>
        </div>
    </div>
</template>
