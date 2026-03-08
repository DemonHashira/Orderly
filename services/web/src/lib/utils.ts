import type { ClassValue } from 'clsx'
import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

export const cn = (...inputs: ClassValue[]) => {
  return twMerge(clsx(inputs))
}

export const isPositiveIntegerString = (value: string) => {
  const parsed = Number(value)
  return Number.isInteger(parsed) && parsed > 0
}
