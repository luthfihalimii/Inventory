<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { PanelLeftClose, PanelLeftOpen } from "@lucide/vue"
import { cn } from "@/lib/utils"
import { Button } from '@/components/ui/button'
import { useSidebar } from "./utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()

const { isMobile, state, toggleSidebar } = useSidebar()
</script>

<template>
  <Button
    data-sidebar="trigger"
    data-slot="sidebar-trigger"
    variant="ghost"
    size="icon"
    aria-label="Buka menu navigasi"
    :class="cn('h-11 w-auto px-2.5 md:h-9 md:min-h-11 md:w-9 md:px-0', props.class)"
    @click="toggleSidebar"
  >
    <PanelLeftOpen v-if="isMobile || state === 'collapsed'" />
    <PanelLeftClose v-else />
    <span class="text-sm font-medium md:sr-only">Menu</span>
  </Button>
</template>
