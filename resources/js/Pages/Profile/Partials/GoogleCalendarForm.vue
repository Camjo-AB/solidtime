<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import ActionSection from '@/Components/ActionSection.vue';
import SectionBorder from '@/Components/SectionBorder.vue';
import PrimaryButton from '@/packages/ui/src/Buttons/PrimaryButton.vue';
import SecondaryButton from '@/packages/ui/src/Buttons/SecondaryButton.vue';
import { useGoogleCalendarStatus } from '@/utils/useGoogleCalendar';

const { enabled, connected, email, isLoading } = useGoogleCalendarStatus();

// Result of the OAuth round trip, set by the server redirect (?google_calendar=...)
const result = new URLSearchParams(window.location.search).get('google_calendar');
const resultMessage = computed(() => {
    switch (result) {
        case 'connected':
            return { text: 'Google Calendar connected.', error: false };
        case 'disconnected':
            return { text: 'Google Calendar disconnected.', error: false };
        case 'cancelled':
            return { text: 'Connecting Google Calendar was cancelled.', error: false };
        case 'error':
            return {
                text: 'Google Calendar could not be connected. Use your company Google account and try again.',
                error: true,
            };
        case 'not_configured':
            return { text: 'Google Calendar is not set up on this server.', error: true };
        default:
            return null;
    }
});

function connect() {
    // Full page navigation: the server redirects on to Google's consent screen
    window.location.href = '/google-calendar/connect';
}

function disconnect() {
    router.delete('/google-calendar');
}
</script>

<template>
    <div v-if="enabled || resultMessage">
        <ActionSection id="google-calendar">
            <template #title>Google Calendar</template>

            <template #description>
                Show the meetings of your Google Calendar in the calendar view and register them
                with one click. Read-only: solidtime never changes your calendar.
            </template>

            <template #content>
                <div class="space-y-4 text-sm" data-testid="google_calendar_section">
                    <p
                        v-if="resultMessage"
                        :class="resultMessage.error ? 'text-red-500' : 'text-text-secondary'">
                        {{ resultMessage.text }}
                    </p>
                    <template v-if="enabled && !isLoading">
                        <p v-if="connected" class="text-text-primary">
                            Connected as <span class="font-medium">{{ email }}</span>
                        </p>
                        <p v-else class="text-text-secondary">Not connected.</p>
                        <div>
                            <SecondaryButton v-if="connected" @click="disconnect">
                                Disconnect
                            </SecondaryButton>
                            <PrimaryButton v-else @click="connect"
                                >Connect Google Calendar</PrimaryButton
                            >
                        </div>
                    </template>
                </div>
            </template>
        </ActionSection>

        <SectionBorder />
    </div>
</template>
