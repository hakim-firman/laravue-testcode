export interface EnrichedEvent {
    id: string;
    type: string;
    status: string;
    created_time: number | null;
    latitude: number | null;
    longitude: number | null;
    name: string;
    description: string | null;
    geocoded_address: string | null;
    city: string | null;
    country: string | null;
    starts_at: number;
    ends_at: number | null;
    venue_name: string | null;
    capacity: number | null;
    min_price: number | null;
    currency: string;
    images: string[];
    user: { id: number; name: string } | null;
}

export interface EventFilters {
    status: string | null;
    type: string | null;
    location: string | null;
    from: string | null;
    to: string | null;
}

export interface EventDataResponse {
    data: EnrichedEvent[];
    current_page: number;
    last_page: number;
    total: number;
    stats: { ms: number; bytes: number };
}
