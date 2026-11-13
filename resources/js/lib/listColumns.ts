/**
 * Widths of the optional columns in the list view, shared by the header
 * and the rows so both line up.
 */
export const LIST_COLUMNS = {
    comments: 'flex w-10 justify-end',
    assignees: 'hidden w-20 justify-end sm:flex',
    time: 'hidden w-16 justify-end sm:flex',
    date: 'flex w-24 sm:w-32',
    status: 'flex w-28 justify-end',
} as const;
