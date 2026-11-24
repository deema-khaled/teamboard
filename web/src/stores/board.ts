import { api } from '@/lib/api';
import { connectEcho } from '@/lib/echo';
import type { Board, BoardEvent, Card, Column, Comment, PublicUser } from '@/types';
import { defineStore } from 'pinia';
import { ref } from 'vue';

/**
 * State of the open board. Local edits are optimistic (the UI moves first,
 * the API confirms); edits by teammates arrive over the presence channel
 * and are patched in by `apply()`.
 */
export const useBoard = defineStore('board', () => {
    const board = ref<Board | null>(null);
    const online = ref<PublicUser[]>([]);
    const lastRemote = ref<{ actor: PublicUser | null; text: string } | null>(null);
    const deleted = ref(false);
    let channelName: string | null = null;

    const columns = (): Column[] => board.value?.columns ?? [];
    const findColumn = (id: number) => columns().find((c) => c.id === id);
    const findCard = (id: number): Card | undefined => columns().flatMap((c) => c.cards).find((c) => c.id === id);

    async function open(id: number): Promise<void> {
        close();
        deleted.value = false;
        const { data } = await api.get(`/boards/${id}`);
        board.value = data.data;
        subscribe(id);
    }

    function close(): void {
        if (channelName) window.Echo?.leave(channelName);
        channelName = null;
        board.value = null;
        online.value = [];
    }

    function subscribe(id: number): void {
        channelName = `board.${id}`;
        connectEcho()
            .join(channelName)
            .here((users: PublicUser[]) => (online.value = users))
            .joining((user: PublicUser) => online.value.push(user))
            .leaving((user: PublicUser) => (online.value = online.value.filter((u) => u.id !== user.id)))
            .listen('.board.changed', (event: BoardEvent) => apply(event));
    }

    // --- Remote changes ---------------------------------------------------------

    function apply(event: BoardEvent): void {
        if (!board.value) return;
        const { type, data, actor } = event;

        switch (type) {
            case 'card.created':
            case 'card.updated':
                upsertCard(data.card);
                break;
            case 'card.moved':
                upsertCard(data.card);
                applyOrders(data.orders);
                break;
            case 'card.deleted':
                removeCard(data.id);
                break;
            case 'comment.created':
            case 'comment.deleted': {
                const card = findCard(data.card_id);
                if (card) card.comments_count = data.comments_count;
                break;
            }
            case 'column.created':
            case 'column.updated':
            case 'column.reordered':
            case 'column.deleted':
                syncColumns(data.columns);
                break;
            case 'member.joined':
                board.value.members.push(data.member);
                break;
            case 'member.removed':
                board.value.members = board.value.members.filter((m) => m.id !== data.user_id);
                break;
            case 'board.updated':
                Object.assign(board.value, data);
                break;
            case 'board.deleted':
                deleted.value = true;
                break;
        }

        lastRemote.value = { actor, text: describe(type, data) };
    }

    function upsertCard(card: Card): void {
        const existing = findCard(card.id);
        if (existing && existing.column_id === card.column_id) {
            Object.assign(existing, card);
            return;
        }
        if (existing) removeCard(card.id);
        const column = findColumn(card.column_id);
        if (column) column.cards.splice(Math.min(card.position, column.cards.length), 0, card);
    }

    function removeCard(id: number): void {
        for (const column of columns()) column.cards = column.cards.filter((c) => c.id !== id);
    }

    /** Server-authoritative order: { columnId: [cardId, …] }. */
    function applyOrders(orders: Record<string, number[]>): void {
        const all = new Map(columns().flatMap((c) => c.cards).map((c) => [c.id, c]));
        for (const [columnId, ids] of Object.entries(orders)) {
            const column = findColumn(Number(columnId));
            if (!column) continue;
            column.cards = ids
                .filter((id) => all.has(id))
                .map((id, position) => Object.assign(all.get(id)!, { column_id: column.id, position }));
        }
    }

    function syncColumns(list: { id: number; name: string; position: number }[]): void {
        if (!board.value) return;
        const existing = new Map(columns().map((c) => [c.id, c]));
        board.value.columns = list.map((c) => ({ ...c, cards: existing.get(c.id)?.cards ?? [] }));
    }

    function describe(type: string, data: Record<string, any>): string {
        return (
            {
                'card.created': `added “${data.card?.title}”`,
                'card.updated': `updated “${data.card?.title}”`,
                'card.moved': `moved “${data.card?.title}”`,
                'card.deleted': 'deleted a card',
                'comment.created': 'commented on a card',
                'column.created': 'added a list',
                'column.reordered': 'reordered the lists',
                'member.joined': 'joined the board',
            }[type] ?? 'changed the board'
        );
    }

    // --- Local actions (optimistic) ---------------------------------------------

    /** Called after vuedraggable already moved the card in the UI. */
    async function moveCard(card: Card, columnId: number, position: number): Promise<void> {
        try {
            const { data } = await api.patch(`/cards/${card.id}/move`, { column_id: columnId, position });
            applyOrders(data.data.orders);
        } catch (error) {
            await reload(); // put things back the way the server has them
            throw error;
        }
    }

    async function addCard(columnId: number, title: string): Promise<void> {
        const { data } = await api.post(`/columns/${columnId}/cards`, { title });
        upsertCard(data.data);
    }

    async function updateCard(card: Card, changes: Partial<Card> & { assignee_id?: number | null }): Promise<Card> {
        const { data } = await api.patch(`/cards/${card.id}`, changes);
        upsertCard(data.data);
        return data.data;
    }

    async function deleteCard(card: Card): Promise<void> {
        removeCard(card.id);
        await api.delete(`/cards/${card.id}`);
    }

    async function addColumn(name: string): Promise<void> {
        const { data } = await api.post(`/boards/${board.value!.id}/columns`, { name });
        board.value!.columns!.push({ ...data.data, cards: [] });
    }

    async function renameColumn(column: Column, name: string): Promise<void> {
        column.name = name;
        await api.patch(`/columns/${column.id}`, { name });
    }

    async function deleteColumn(column: Column): Promise<void> {
        board.value!.columns = columns().filter((c) => c.id !== column.id);
        await api.delete(`/columns/${column.id}`);
    }

    async function reorderColumns(): Promise<void> {
        await api.put(`/boards/${board.value!.id}/columns/order`, { order: columns().map((c) => c.id) });
    }

    async function addComment(card: Card, body: string): Promise<Comment> {
        const { data } = await api.post(`/cards/${card.id}/comments`, { body });
        const live = findCard(card.id);
        if (live) live.comments_count = (live.comments_count ?? 0) + 1;
        return data.data;
    }

    async function reload(): Promise<void> {
        if (!board.value) return;
        const { data } = await api.get(`/boards/${board.value.id}`);
        board.value = data.data;
    }

    return {
        board, online, lastRemote, deleted,
        open, close, apply, findCard,
        moveCard, addCard, updateCard, deleteCard,
        addColumn, renameColumn, deleteColumn, reorderColumns, addComment, reload,
    };
});
