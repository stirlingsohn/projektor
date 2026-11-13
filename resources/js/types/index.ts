import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface ProjectSummary {
    id: number;
    parent_id: number | null;
    name: string;
    color: string;
    client_can_see_time: boolean;
}

export interface ListSummary {
    id: number;
    project_id: number;
    name: string;
}

/** A list as named on the project page, with its subproject when it lives in one. */
export interface ListLabel {
    id: number;
    name: string;
    project: string | null;
}

export interface ProjectDashboard {
    summary: { open: number; overdue: number; done: number; total: number };
    /** Progress of the project's own lists, keyed by list id. */
    listStats: Record<number, { total: number; done: number; overdue: number; next_due: string | null }>;
    upcoming: { id: number; title: string; due_date: string; status: { name: string; color: string }; list: ListLabel }[];
    /** `null` when the viewer may not see the time. */
    time: { total: number; week: number; lists: (ListLabel & { seconds: number })[] } | null;
}

export interface DocumentSummary {
    id: number;
    project_id: number;
    title: string;
}

export interface Attachment {
    id: number;
    task_id: number | null;
    task_title: string | null;
    name: string;
    size: number;
    mime_type: string;
    /** Shows the file in the browser where that is safe. */
    url: string;
    download_url: string;
    created_at: string;
}

export type AccessLevel = 'view' | 'comment' | 'edit';

export interface Sharing {
    members: { id: number; name: string; email: string; level: AccessLevel }[];
    /** Access that comes from a project further up. */
    inherited: { id: number; name: string; email: string; level: AccessLevel; source: string; source_id: number }[];
    invitations: { id: number; email: string; level: AccessLevel; expires_at: string }[];
}

export interface Comment {
    id: number;
    task_id: number;
    user_id: number | null;
    user: string | null;
    body: string;
    created_at: string;
    edited: boolean;
}

export interface Status {
    id: number;
    name: string;
    color: string;
    is_done: boolean;
}

export interface Task {
    id: number;
    status_id: number;
    title: string;
    description: string | null;
    start_date: string | null;
    due_date: string | null;
    completed_at: string | null;
    /** IDs of the tasks that must be finished before this one can start. */
    depends_on: number[];
    /** Seconds on finished time entries; `null` when the viewer may not see time. */
    tracked_seconds: number | null;
    comments_count: number;
}

export interface TimeEntry {
    id: number;
    task_id: number;
    user: string;
    /** The day the time was spent, `YYYY-MM-DD`. */
    date: string;
    seconds: number;
    note: string | null;
}

export interface RunningTimer {
    id: number;
    task_id: number;
    task_title: string;
    list_id: number;
    started_at: string;
}

export interface SharedData {
    name: string;
    auth: Auth;
    translations: Record<string, string>;
    timer: RunningTimer | null;
    projects: ProjectSummary[];
    lists: ListSummary[];
    documents: DocumentSummary[];
    maxUploadMb: number;
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    role: 'owner' | 'client';
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
