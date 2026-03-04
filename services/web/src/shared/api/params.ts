export const compactParams = <T extends Record<string, unknown>>(
  params: T,
): Record<string, unknown> => {
  return Object.fromEntries(
    Object.entries(params)
      .filter(([, value]) => value !== undefined && value !== null && value !== '')
      .map(([key, value]) => {
        if (typeof value === 'boolean') {
          return [key, value ? 1 : 0]
        }

        return [key, value]
      }),
  )
}
