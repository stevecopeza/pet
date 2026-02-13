# PET Command Surface -- API Contract v1.0

GET /feed/my Returns filtered feed for logged-in user.

Response: \[ { "id": UUID, "event_type": "SLABreach", "classification":
"critical", "title": string, "summary": string, "pinned": boolean,
"expires_at": datetime, "created_at": datetime }\]

GET /feed/global Returns global pulse feed.

POST /announcements Create announcement (role-restricted).

POST /announcements/{id}/acknowledge Body: { "device_info": string,
"gps_lat": decimal optional, "gps_lng": decimal optional }

POST /feed/{id}/react Body: { "reaction_type": "concern" }

GET /announcements/pending Returns announcements requiring
acknowledgement.

Validation Rules: - GPS required if gps_required = true. - Cannot
acknowledge expired announcement. - Reaction type must match enum.
