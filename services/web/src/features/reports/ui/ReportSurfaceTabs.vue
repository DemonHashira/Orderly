<script setup lang="ts">
import { computed, ref } from 'vue'

const props = withDefaults(
  defineProps<{
    defaultValue?: string
  }>(),
  {
    defaultValue: 'overview',
  },
)

const activeTab = ref(props.defaultValue)

const panelKey = computed(() => `report-tab-${activeTab.value}`)

const tabs = [
  { value: 'overview', label: 'Overview' },
  { value: 'exceptions', label: 'Exceptions' },
  { value: 'breakdowns', label: 'Breakdowns' },
] as const
</script>

<template>
  <div class="flex flex-col gap-4">
    <div
      role="tablist"
      aria-label="Report sections"
      class="bg-muted text-muted-foreground inline-flex h-9 w-full items-center justify-center rounded-lg p-[3px]"
    >
      <button
        v-for="tab in tabs"
        :key="tab.value"
        type="button"
        role="tab"
        :aria-selected="activeTab === tab.value"
        :tabindex="activeTab === tab.value ? 0 : -1"
        data-slot="tabs-trigger"
        class="focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:outline-ring inline-flex h-[calc(100%-1px)] flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-md border border-transparent px-2 py-1 text-sm font-medium whitespace-nowrap transition-[color,box-shadow] focus-visible:ring-[3px] focus-visible:outline-1"
        :class="
          activeTab === tab.value
            ? 'bg-background text-foreground shadow-sm'
            : 'text-foreground dark:text-muted-foreground'
        "
        @click="activeTab = tab.value"
      >
        {{ tab.label }}
      </button>
    </div>

    <Transition name="dashboard-section" mode="out-in" appear>
      <div :key="panelKey" class="flex flex-col gap-4">
        <div v-if="activeTab === 'overview'" class="flex flex-col gap-4">
          <slot name="overview" />
        </div>
        <div v-else-if="activeTab === 'exceptions'" class="flex flex-col gap-4">
          <slot name="exceptions" />
        </div>
        <div v-else class="flex flex-col gap-4">
          <slot name="breakdowns" />
        </div>
      </div>
    </Transition>
  </div>
</template>
