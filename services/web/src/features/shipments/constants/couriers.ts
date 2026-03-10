export const BULGARIA_COURIER_OPTIONS = ['Econt', 'Speedy', 'BOX NOW', 'Sameday'] as const

export type BulgariaCourierOption = (typeof BULGARIA_COURIER_OPTIONS)[number]
