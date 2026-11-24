export interface PublicUser {
    id: number;
    name: string;
    initials: string;
    color: string;
}

export interface Me extends PublicUser {
    email: string;
    unread_notifications?: number;
}

export interface Member extends PublicUser {
    role: 'owner' | 'member';
}

export type Label = 'red' | 'orange' | 'yellow' | 'green' | 'blue' | 'purple';

export interface Comment {
    id: number;
    card_id: number;
    body: string;
    user: PublicUser;
    created_at: string;
}

export interface Card {
    id: number;
    column_id: number;
    title: string;
    description: string | null;
    label: Label | null;
    due_date: string | null;
    is_overdue: boolean;
    position: number;
    assignee: PublicUser | null;
    comments_count?: number;
    comments?: Comment[];
}

export interface Column {
    id: number;
    name: string;
    position: number;
    cards: Card[];
}

export interface Board {
    id: number;
    name: string;
    description: string | null;
    color: string;
    is_owner: boolean;
    cards_count?: number;
    members: Member[];
    columns?: Column[];
    updated_at: string;
}

export interface Activity {
    id: number;
    type: string;
    description: string;
    user: PublicUser | null;
    created_at: string;
}

export interface AppNotification {
    id: string;
    type: string;
    message: string;
    board_id: number;
    card_id: number;
    actor: PublicUser | null;
    read: boolean;
    created_at: string;
}

/** Payload of the `board.changed` broadcast event. */
export interface BoardEvent {
    type: string;
    data: Record<string, any>;
    actor: PublicUser | null;
}
