<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { CalendarDays, Clock, MapPin, Users } from '@lucide/vue';
import { computed } from 'vue';
import EventCardImage from '@/components/events/EventCardImage.vue';
import EventTypeBadge from '@/components/events/EventTypeBadge.vue';
import { Button } from '@/components/ui/button';
import { useEventFormat } from '@/composables/useEventFormat';

interface EventDetail {
    id: string;
    type: string;
    status: string;
    created_time: number | null;
    geocoded_address: string | null;
    geocoded_city: string | null;
    geocoded_country: string | null;
    images: { path: string }[];
    payload: {
        name?: string;
        description?: string;
        venue?: { name?: string; capacity?: number };
        schedule?: { starts_at?: number; ends_at?: number };
        pricing?: { currency?: string; min_price?: number };
    };
}

interface AttendeeView {
    name: string;
    status: string;
}

const props = defineProps<{
    event: EventDetail;
    attendees: AttendeeView[];
    canRegister: boolean;
}>();

const { formatDate, formatTime, formatPrice, formatCapacity } =
    useEventFormat();

const startsAt = computed(
    () => props.event.payload.schedule?.starts_at ?? props.event.created_time,
);
const location = computed(
    () => props.event.geocoded_address ?? 'Location to be announced',
);
const venue = computed(() => props.event.payload.venue?.name ?? null);
const price = computed(() =>
    formatPrice(
        props.event.payload.pricing?.min_price ?? null,
        props.event.payload.pricing?.currency ?? 'USD',
    ),
);
const heroImage = computed(() => props.event.images[0]?.path);

const goingCount = computed(
    () => props.attendees.filter((a) => a.status === 'going').length,
);
const interestedCount = computed(
    () => props.attendees.filter((a) => a.status === 'interested').length,
);

const form = useForm({
    name: '',
    email: '',
    status: 'going',
    website: '', // honeypot — stays empty
});

function submit() {
    form.post(`/events/${props.event.id}/attendees`, {
        preserveScroll: true,
        onSuccess: () => form.reset('name', 'email', 'website'),
    });
}
</script>

<template>
    <Head :title="event.payload.name ?? `Event ${event.id}`" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4">
        <Link href="/events" class="text-sm text-primary hover:underline">
            ← Back to events
        </Link>

        <!-- Hero -->
        <div class="relative overflow-hidden rounded-xl border">
            <EventCardImage :src="heroImage" :alt="event.payload.name ?? ''" />
            <div class="absolute top-3 left-3 flex gap-2">
                <EventTypeBadge :type="event.type" />
                <span
                    v-if="event.status !== 'published'"
                    class="inline-flex items-center rounded-full bg-background/80 px-2.5 py-0.5 text-xs font-medium capitalize backdrop-blur-sm"
                >
                    {{ event.status.replace('_', ' ') }}
                </span>
            </div>
        </div>

        <!-- Title + meta -->
        <div class="flex flex-col gap-3">
            <h1 class="text-2xl font-semibold tracking-tight">
                {{ event.payload.name ?? 'Untitled Event' }}
            </h1>

            <div
                class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-muted-foreground"
            >
                <span class="flex items-center gap-2">
                    <CalendarDays class="size-4" />
                    {{ formatDate(startsAt) }}
                </span>
                <span class="flex items-center gap-2">
                    <Clock class="size-4" />
                    {{ formatTime(startsAt) }}
                </span>
                <span class="flex items-center gap-2">
                    <MapPin class="size-4" />
                    {{ location
                    }}<template v-if="venue"> · {{ venue }}</template>
                </span>
                <span
                    v-if="event.payload.venue?.capacity"
                    class="flex items-center gap-2"
                >
                    <Users class="size-4" />
                    {{ formatCapacity(event.payload.venue.capacity) }}
                </span>
                <span v-if="price" class="font-medium text-foreground">
                    {{ price }}
                </span>
            </div>

            <p
                v-if="event.payload.description"
                class="leading-relaxed text-foreground/90"
            >
                {{ event.payload.description }}
            </p>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <!-- RSVP -->
            <div class="flex flex-col gap-4 rounded-xl border p-5">
                <h2 class="font-semibold">Register</h2>

                <template v-if="canRegister">
                    <form class="flex flex-col gap-3" @submit.prevent="submit">
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-medium" for="name">
                                Name
                            </label>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            />
                            <span
                                v-if="form.errors.name"
                                class="text-xs text-red-600"
                            >
                                {{ form.errors.name }}
                            </span>
                        </div>

                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-medium" for="email">
                                Email
                            </label>
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            />
                            <span
                                v-if="form.errors.email"
                                class="text-xs text-red-600"
                            >
                                {{ form.errors.email }}
                            </span>
                        </div>

                        <!-- Honeypot: hidden from users, tempting to bots -->
                        <input
                            v-model="form.website"
                            type="text"
                            name="website"
                            tabindex="-1"
                            autocomplete="off"
                            class="hidden"
                            aria-hidden="true"
                        />

                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="flex-1 rounded-md border px-3 py-2 text-sm font-medium transition-colors"
                                :class="
                                    form.status === 'going'
                                        ? 'border-primary bg-primary/10 text-primary'
                                        : 'border-input hover:bg-muted'
                                "
                                @click="form.status = 'going'"
                            >
                                I'm going
                            </button>
                            <button
                                type="button"
                                class="flex-1 rounded-md border px-3 py-2 text-sm font-medium transition-colors"
                                :class="
                                    form.status === 'interested'
                                        ? 'border-primary bg-primary/10 text-primary'
                                        : 'border-input hover:bg-muted'
                                "
                                @click="form.status = 'interested'"
                            >
                                Interested
                            </button>
                        </div>

                        <Button type="submit" :disabled="form.processing">
                            Register
                        </Button>
                    </form>
                </template>

                <p v-else class="text-sm text-muted-foreground">
                    Registration is closed for this event.
                </p>
            </div>

            <!-- Attendee list -->
            <div class="flex flex-col gap-4 rounded-xl border p-5">
                <div class="flex items-baseline justify-between">
                    <h2 class="font-semibold">Attendees</h2>
                    <span class="text-sm text-muted-foreground">
                        {{ goingCount }} going ·
                        {{ interestedCount }} interested
                    </span>
                </div>

                <ul v-if="attendees.length" class="flex flex-col gap-2 text-sm">
                    <li
                        v-for="(attendee, i) in attendees"
                        :key="i"
                        class="flex items-center justify-between"
                    >
                        <span class="truncate">{{ attendee.name }}</span>
                        <span
                            class="ml-2 shrink-0 text-xs text-muted-foreground capitalize"
                        >
                            {{ attendee.status }}
                        </span>
                    </li>
                </ul>

                <p v-else class="text-sm text-muted-foreground">
                    Be the first to register.
                </p>
            </div>
        </div>
    </div>
</template>
