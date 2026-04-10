export function isElementOverflowing(element: {
  scrollWidth: number
  clientWidth: number
  scrollHeight?: number
  clientHeight?: number
}): boolean {
  const isHorizontallyOverflowing = element.scrollWidth > element.clientWidth
  const isVerticallyOverflowing =
    typeof element.scrollHeight === 'number' &&
    typeof element.clientHeight === 'number' &&
    element.scrollHeight > element.clientHeight

  return isHorizontallyOverflowing || isVerticallyOverflowing
}
