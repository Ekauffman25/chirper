# Feature Documentation: User Profiles & Search

This document provides a detailed technical explanation of how user profiles and search functionality work in the Chirper application. This guide will help you understand the architecture and recreate similar features in future projects.

---

## Table of Contents

1. [User Profiles](#user-profiles)
2. [Search Functionality](#search-functionality)
3. [Architecture Patterns](#architecture-patterns)
4. [Database Relationships](#database-relationships)

---

## User Profiles

### Overview

User profiles display a specific user's information and all their chirps. It's a public-facing page where anyone can view a user's activity.

### Architecture

```
Routes → Controller → Model → Database
  ↓         ↓           ↓
/users/{user} → UserController@show → User Model → Users table
                                      ↓
                                    Chirp Model (relationship)
                                      ↓
                                    Chirps table
```

### 1. Routing

**File**: [routes/web.php](routes/web.php)

```php
Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
```

**How it works**:
- Route pattern: `/users/{user}` 
- The `{user}` is a route parameter that Laravel automatically resolves using **Route Model Binding**
- Laravel automatically finds the User with that ID and injects it into the controller
- Named route `users.show` allows you to generate URLs with `route('users.show', $user)`

**Route Model Binding Example**:
```
URL: /users/5
→ Laravel finds User with id=5
→ Automatically passes $user object to controller
```

### 2. Model Definition

**File**: [app/Models/User.php](app/Models/User.php)

```php
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public function chirps(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Chirp::class);
    }
}
```

**Key Concepts**:

- **Relationship**: `chirps()` defines a one-to-many relationship
  - One User has many Chirps
  - The foreign key defaults to `user_id` in the chirps table
  - This creates a query builder that can filter chirps by user

- **How to use**:
  ```php
  $user = User::find(1);
  $userChirps = $user->chirps()->get();  // Get all chirps by this user
  $latestChirps = $user->chirps()->latest()->get();  // Get latest chirps
  ```

### 3. Controller Logic

**File**: [app/Http/Controllers/UserController.php](app/Http/Controllers/UserController.php)

```php
class UserController extends Controller
{
    public function show(User $user)
    {
        // Get latest chirps for this user, paginated 20 per page
        $chirps = $user->chirps()
            ->latest()              // Order by created_at descending
            ->with('user')          // Eager load user relationship
            ->paginate(20);         // Pagination (20 items per page)

        return view('users.show', [
            'user' => $user,
            'chirps' => $chirps,
        ]);
    }
}
```

**Breaking it down**:

1. **`$user->chirps()`**: Gets the query builder for all chirps by this user
2. **`->latest()`**: Orders by `created_at` descending (newest first)
3. **`->with('user')`**: Eager loads the user relationship for each chirp
   - Prevents N+1 queries (loads all chirps' users in one query instead of many)
4. **`->paginate(20)`**: Returns paginated results with 20 items per page
   - Returns pagination object with navigation links
5. **Pass to view**: Both `$user` and `$chirps` are available in the template

**Why `->with('user')`?**
```php
// Without eager loading (N+1 problem - slow):
$chirps = $user->chirps()->get();  // 1 query
foreach($chirps as $chirp) {
    echo $chirp->user->name;  // 1 query per chirp = N queries!
}
// Total: 1 + N queries

// With eager loading (fast):
$chirps = $user->chirps()->with('user')->get();  // 1 query
foreach($chirps as $chirp) {
    echo $chirp->user->name;  // No query - already loaded!
}
// Total: 1 query
```

### 4. View/Template

**File**: [resources/views/users/show.blade.php](resources/views/users/show.blade.php)

```blade
<x-layout>
    <x-slot:title>{{ $user->name }}</x-slot:title>

    <div class="max-w-3xl mx-auto">
        <!-- Profile Header Card -->
        <div class="card bg-base-100 shadow">
            <div class="card-body space-y-4">
                <!-- Avatar and User Info -->
                <div class="flex items-center gap-4">
                    <div class="avatar">
                        <img src="https://avatars.laravel.cloud/{{ urlencode($user->email) }}" 
                             alt="{{ $user->name }}'s avatar" />
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold">{{ $user->name }}</h1>
                        <p class="text-base-content/60">User ID: {{ $user->id }}</p>
                    </div>
                </div>

                <p class="text-sm text-base-content/70">
                    Showing the latest chirps from this profile.
                </p>
            </div>
        </div>

        <!-- Chirps List -->
        <div class="mt-8">
            <h2 class="text-2xl font-semibold mb-4">
                {{ $chirps->count() }} chirp{{ $chirps->count() === 1 ? '' : 's' }}
                by {{ $user->name }}
            </h2>

            <div class="space-y-4">
                @forelse ($chirps as $chirp)
                    <x-chirp :chirp="$chirp" />
                @empty
                    <div class="card bg-base-100 shadow p-6 text-center">
                        <p class="text-base-content/60">
                            No chirps found for this user yet.
                        </p>
                    </div>
                @endforelse
            </div>

            <!-- Pagination Links -->
            <div class="mt-6">
                {{ $chirps->links() }}
            </div>
        </div>
    </div>
</x-layout>
```

**Template Features**:

1. **`@forelse`**: Loop through chirps, show empty message if none exist
2. **`{{ $chirps->count() }}`**: Display number of chirps
3. **`{{ $chirps->links() }}`**: Display pagination buttons
4. **`<x-chirp :chirp="$chirp" />`**: Reuse chirp component for each chirp
5. **Avatar generation**: Uses Laravel Cloud's avatar service (generates avatar from email)

### 5. Data Flow Summary

```
User visits /users/5
    ↓
Route Model Binding finds User with id=5
    ↓
UserController@show($user) executes
    ↓
Query: SELECT * FROM chirps WHERE user_id=5 ORDER BY created_at DESC LIMIT 20
    ↓
View renders with $user and $chirps
    ↓
Display user profile with paginated chirps
```

---

## Search Functionality

### Overview

Search allows users to find:
- Other users (by name or ID)
- Chirps (by content/message)

It features both full-page results and real-time suggestions while typing.

### Architecture

```
Routes → Controllers ← JavaScript → API Endpoints
  ↓           ↓
/search    SearchController@index     (Full results page)
/search    SearchController@suggestions (JSON API)
           Database queries
```

### 1. Routing

**File**: [routes/web.php](routes/web.php)

```php
Route::get('/search', [SearchController::class, 'index'])->name('search.index');
Route::get('/search/suggestions', [SearchController::class, 'suggestions'])->name('search.suggestions');
```

**Routes explained**:

1. **`/search`** - Full search results page
   - Returns HTML view with complete results
   - Query parameter: `?query=searchterm`
   - Named route: `route('search.index')`

2. **`/search/suggestions`** - Live suggestions API
   - Returns JSON data for autocomplete
   - Query parameter: `?query=searchterm`
   - Called by JavaScript while user types
   - Named route: `route('search.suggestions')`

### 2. Controller Logic

**File**: [app/Http/Controllers/SearchController.php](app/Http/Controllers/SearchController.php)

#### Method 1: Index (Full Results)

```php
public function index(Request $request)
{
    // Get search query from URL parameter
    $query = trim($request->query('query', ''));

    // Initialize empty collections
    $users = collect();
    $chirps = collect();

    // Only search if query is not empty
    if ($query !== '') {
        // Search users by ID (if numeric) or name
        $users = User::query()
            ->when(is_numeric($query), fn ($builder) => $builder->where('id', $query))
            ->orWhere('name', 'like', "%{$query}%")
            ->limit(20)
            ->get();

        // Search chirps by message content
        $chirps = Chirp::with('user')
            ->where('message', 'like', "%{$query}%")
            ->latest()
            ->limit(50)
            ->get();
    }

    return view('search.results', [
        'query' => $query,
        'users' => $users,
        'chirps' => $chirps,
    ]);
}
```

**Query Breakdown**:

**User Search**:
```php
User::query()
    ->when(is_numeric($query), fn ($builder) => $builder->where('id', $query))
    ->orWhere('name', 'like', "%{$query}%")
```
- If user typed a number: search by ID
- Otherwise: search by name containing the query
- Example: Query `"john"` finds users with name containing "john"
- Example: Query `"5"` finds user with id=5

**Chirp Search**:
```php
Chirp::with('user')
    ->where('message', 'like', "%{$query}%")
    ->latest()
    ->limit(50)
```
- Search by message content
- `LIKE "%query%"` matches anywhere in message
- Order by newest first
- Limit to 50 results
- Eager load user to avoid N+1 queries

#### Method 2: Suggestions (API)

```php
public function suggestions(Request $request)
{
    $query = trim($request->query('query', ''));

    // Return empty if no query
    if ($query === '') {
        return response()->json(['users' => [], 'chirps' => []]);
    }

    // Get up to 5 matching users (only id, name, email)
    $users = User::query()
        ->when(is_numeric($query), fn ($builder) => $builder->where('id', $query))
        ->orWhere('name', 'like', "%{$query}%")
        ->limit(5)
        ->get(['id', 'name', 'email']);

    // Get up to 5 matching chirps
    $chirps = Chirp::with('user')
        ->where('message', 'like', "%{$query}%")
        ->latest()
        ->limit(5)
        ->get(['id', 'message', 'user_id'])
        ->load('user:id,name');

    // Return JSON response
    return response()->json([
        'users' => $users,
        'chirps' => $chirps,
    ]);
}
```

**Key Differences from Index**:
- Returns JSON instead of HTML
- Limits to 5 results (faster for suggestions)
- Uses `->get(['specific', 'columns'])` to reduce data transfer
- Returns early if query is empty

**Why separate endpoints?**
- **Full search** (`/search`): Returns full results with styling and HTML
- **Suggestions** (`/search/suggestions`): Returns minimal JSON for quick suggestions

### 3. JavaScript Implementation

**File**: [resources/js/app.js](resources/js/app.js)

```javascript
document.addEventListener('DOMContentLoaded', () => {
    // Get form and elements
    const searchForm = document.querySelector('form[data-suggestions-url]');
    if (!searchForm) return;

    const searchInput = searchForm.querySelector('#search-query');
    const suggestionsPanel = searchForm.querySelector('#search-suggestions');
    const suggestionsUrl = searchForm.dataset.suggestionsUrl;
    let debounceTimer = null;
    let currentQuery = '';

    // Format user suggestion item
    const formatUserItem = (user) => {
        return `
            <a href="/users/${user.id}" class="block px-4 py-3 hover:bg-base-200">
                <div class="font-semibold">${user.name}</div>
                <div class="text-sm text-base-content/60">User ID: ${user.id}</div>
            </a>
        `;
    };

    // Format chirp suggestion item
    const formatChirpItem = (chirp) => {
        const preview = chirp.message.length > 80 
            ? `${chirp.message.slice(0, 80)}…` 
            : chirp.message;
        const author = chirp.user ? chirp.user.name : 'Anonymous';

        return `
            <a href="/search?query=${encodeURIComponent(currentQuery)}" 
               class="block px-4 py-3 hover:bg-base-200">
                <div class="font-semibold">${author}</div>
                <div class="text-sm text-base-content/60">${preview}</div>
            </a>
        `;
    };

    // Render suggestions dropdown
    const renderSuggestions = ({ users, chirps }) => {
        if (!users?.length && !chirps?.length) {
            suggestionsPanel.innerHTML = 
                '<div class="px-4 py-3 text-sm text-base-content/60">No matches found.</div>';
            suggestionsPanel.classList.remove('hidden');
            return;
        }

        let html = '';
        if (users?.length) {
            html += '<div class="px-4 py-2 text-xs uppercase text-base-content/60">Users</div>';
            html += users.map(formatUserItem).join('');
        }
        if (chirps?.length) {
            html += '<div class="px-4 py-2 text-xs uppercase text-base-content/60">Chirps</div>';
            html += chirps.map(formatChirpItem).join('');
        }

        suggestionsPanel.innerHTML = html;
        suggestionsPanel.classList.remove('hidden');
    };

    // Hide suggestions
    const hideSuggestions = () => {
        suggestionsPanel.classList.add('hidden');
    };

    // Fetch suggestions from API with debounce
    const updateSuggestions = async (query) => {
        currentQuery = query;

        if (!query) {
            hideSuggestions();
            return;
        }

        try {
            const response = await fetch(
                `${suggestionsUrl}?query=${encodeURIComponent(query)}`
            );
            if (!response.ok) {
                hideSuggestions();
                return;
            }

            const data = await response.json();
            renderSuggestions(data);
        } catch (error) {
            hideSuggestions();
        }
    };

    // Event listener: Input with debounce
    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => 
            updateSuggestions(searchInput.value.trim()), 
            200  // Wait 200ms after user stops typing
        );
    });

    // Event listener: Show suggestions on focus
    searchInput.addEventListener('focus', () => {
        if (searchInput.value.trim()) {
            updateSuggestions(searchInput.value.trim());
        }
    });

    // Event listener: Hide suggestions when clicking outside
    document.addEventListener('click', (event) => {
        if (!searchForm.contains(event.target)) {
            hideSuggestions();
        }
    });
});
```

**How it works**:

1. **Debouncing**: 
   - User types → clear timer → wait 200ms → make API call
   - Prevents too many requests to server
   - Only sends one request after user stops typing

2. **Formatting**:
   - Creates HTML string for each user/chirp suggestion
   - User: shows name and ID
   - Chirp: shows preview (max 80 chars) and author

3. **Event Handlers**:
   - `input`: Debounced API call while typing
   - `focus`: Show suggestions if input has text
   - `click` (outside): Hide suggestions

### 4. View/Template

**File**: [resources/views/search/results.blade.php](resources/views/search/results.blade.php)

```blade
<x-layout>
    <x-slot:title>Search results for "{{ $query }}"</x-slot:title>

    <div class="max-w-4xl mx-auto space-y-6">
        <div>
            <h1 class="text-3xl font-bold">Search</h1>
            <p class="text-base-content/60 mt-2">
                Showing matches for "{{ $query ?: 'nothing yet' }}".
            </p>
        </div>

        <!-- Empty state -->
        @if ($query === '')
            <div class="card bg-base-100 shadow p-6">
                <p class="text-base-content/70">
                    Type a name, user ID, or keyword from a chirp to begin searching.
                </p>
            </div>
        @endif

        <!-- Users Results -->
        @if ($users->isNotEmpty())
            <div class="space-y-3">
                <h2 class="text-xl font-semibold">Users</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($users as $resultUser)
                        <a href="{{ route('users.show', $resultUser) }}" 
                           class="card bg-base-100 shadow p-4 hover:bg-base-200 transition">
                            <div class="flex items-center gap-3">
                                <div class="avatar">
                                    <img src="https://avatars.laravel.cloud/{{ urlencode($resultUser->email) }}" 
                                         alt="{{ $resultUser->name }}" />
                                </div>
                                <div>
                                    <div class="font-semibold">{{ $resultUser->name }}</div>
                                    <div class="text-sm text-base-content/60">
                                        User ID: {{ $resultUser->id }}
                                    </div>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Chirps Results -->
        @if ($chirps->isNotEmpty())
            <div class="space-y-3">
                <h2 class="text-xl font-semibold">Chirps</h2>
                <div class="space-y-4">
                    @foreach ($chirps as $chirp)
                        <x-chirp :chirp="$chirp" />
                    @endforeach
                </div>
            </div>
        @endif

        <!-- No results -->
        @if ($query !== '' && $users->isEmpty() && $chirps->isEmpty())
            <div class="card bg-base-100 shadow p-6 text-center">
                <p class="text-base-content/70">
                    We couldn't find any users or chirps matching "{{ $query }}".
                </p>
            </div>
        @endif
    </div>
</x-layout>
```

**Features**:
- Displays users in 2-column grid
- Shows chirps in vertical list
- "No results" message if nothing found
- Linked cards that navigate to user profiles or search

### 5. HTML Structure (Layout)

**File**: [resources/views/components/layout.blade.php](resources/views/components/layout.blade.php)

```blade
<div class="navbar-center flex-1 px-2">
    <div class="w-full max-w-xl">
        <form method="GET" action="{{ route('search.index') }}" 
              data-suggestions-url="{{ route('search.suggestions') }}" 
              class="relative w-full">
            
            <input
                id="search-query"
                name="query"
                value="{{ request('query') }}"
                type="search"
                placeholder="Search users or chirps"
                class="input input-bordered w-full pr-28"
                autocomplete="off"
            />
            
            <button type="submit" class="btn btn-primary btn-sm absolute right-1 top-1">
                Search
            </button>

            <!-- Suggestions dropdown -->
            <div id="search-suggestions" 
                 class="hidden absolute left-0 right-0 z-50 mt-1 rounded-box border border-base-200 bg-base-100 shadow-lg overflow-hidden">
            </div>
        </form>
        
        <!-- Help text (outside form so it doesn't block interaction) -->
        <div class="mt-1 text-xs text-base-content/60">
            Search by user name, user ID, or keywords inside chirps.
        </div>
    </div>
</div>
```

**Key attributes**:
- `data-suggestions-url="{{ route('search.suggestions') }}"` - Passed to JavaScript
- `id="search-query"` - Referenced in JavaScript
- `id="search-suggestions"` - Dropdown container for suggestions
- `autocomplete="off"` - Disable browser autocomplete to show custom suggestions

### 6. Data Flow Summary

**User Types in Search Bar**:
```
User types "john"
    ↓
JavaScript input event fires (with 200ms debounce)
    ↓
Fetch to /search/suggestions?query=john
    ↓
SearchController@suggestions executes
    ↓
Database queries:
  - SELECT * FROM users WHERE id=john OR name LIKE %john% LIMIT 5
  - SELECT * FROM chirps WHERE message LIKE %john% LIMIT 5
    ↓
Controller returns JSON with matching users and chirps
    ↓
JavaScript receives JSON response
    ↓
JavaScript renders dropdown HTML with suggestions
    ↓
User sees dropdown with matching results
    ↓
User clicks suggestion or presses Enter
    ↓
Navigate to full search results page (/search?query=john)
    ↓
SearchController@index executes (shows full results)
```

---

## Architecture Patterns

### 1. Route Model Binding

Used in user profiles to automatically resolve users from route parameters.

```php
// Route definition
Route::get('/users/{user}', [UserController::class, 'show']);

// Automatically converts {user} to User object
public function show(User $user) // Laravel injects the User object
{
    return view('users.show', compact('user'));
}
```

### 2. Eloquent Relationships

Used to query related data efficiently.

```php
// One-to-many relationship
class User extends Model
{
    public function chirps()
    {
        return $this->hasMany(Chirp::class);
    }
}

// Usage
$user = User::find(1);
$chirps = $user->chirps()->latest()->get();
```

### 3. Eager Loading

Prevents N+1 query problems.

```php
// Bad (N+1 queries):
$chirps = Chirp::all();
foreach ($chirps as $chirp) {
    echo $chirp->user->name;  // 1 query per chirp
}

// Good (1 query):
$chirps = Chirp::with('user')->get();
foreach ($chirps as $chirp) {
    echo $chirp->user->name;  // Already loaded
}
```

### 4. API vs View Controllers

**Separate endpoints for different response types**:

```php
// Returns HTML
Route::get('/search', SearchController::class, 'index');

// Returns JSON
Route::get('/search/suggestions', SearchController::class, 'suggestions');
```

### 5. JavaScript Event Handling

**Debouncing**: Wait for user to stop typing before making API call.

```javascript
let debounceTimer = null;

element.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        // Make API call after 200ms of inactivity
        apiCall();
    }, 200);
});
```

### 6. Pagination

Splits large result sets into pages.

```php
$chirps = $user->chirps()->latest()->paginate(20);  // 20 per page

// In view: {{ $chirps->links() }}
```

---

## Database Relationships

### Users Table
```
id (Primary Key)
name
email (unique)
password
created_at
updated_at
```

### Chirps Table
```
id (Primary Key)
user_id (Foreign Key → users.id)
message
created_at
updated_at
```

### Relationship Diagram
```
Users (1)
   ↓
   │ has many
   ↓
Chirps (many)
```

### SQL Example
```sql
-- Get all chirps by user 5
SELECT * FROM chirps WHERE user_id = 5 ORDER BY created_at DESC;

-- Search users
SELECT * FROM users 
WHERE id = 5 OR name LIKE '%john%' 
LIMIT 20;

-- Search chirps
SELECT * FROM chirps 
WHERE message LIKE '%hello%' 
ORDER BY created_at DESC 
LIMIT 50;
```

---

## Implementation Checklist for Recreating

### User Profiles
- [ ] Create User model with `chirps()` relationship
- [ ] Create UserController with `show()` method
- [ ] Add route: `Route::get('/users/{user}', [UserController::class, 'show'])`
- [ ] Create view with user header and chirps list
- [ ] Add pagination to chirps
- [ ] Use eager loading with `->with('user')`

### Search Functionality
- [ ] Create SearchController with `index()` and `suggestions()` methods
- [ ] Add routes for both methods
- [ ] Create search results view
- [ ] Add search form to layout with proper attributes
- [ ] Implement JavaScript with:
  - Event listeners (input, focus, click)
  - Debounce timer
  - API fetch to suggestions endpoint
  - HTML rendering of suggestions
  - Show/hide logic
- [ ] Database queries with:
  - Conditional filtering (ID vs name for users)
  - LIKE operators for partial matching
  - Result limits
  - Eager loading of relationships

