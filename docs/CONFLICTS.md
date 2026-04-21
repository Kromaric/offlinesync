# Conflict Resolution Guide

Complete guide to understanding and configuring conflict resolution in OfflineSync.

---

## 📋 Table of contents

1. [What is a conflict?](#what-is-a-conflict)
2. [The 4 strategies](#the-4-strategies)
3. [Configuration](#configuration)
4. [Use cases](#use-cases)
5. [Custom strategies](#custom-strategies)
6. [Debugging](#debugging)

---

## ❓ What is a conflict?

A conflict occurs when **the same data** has been modified both **locally** (on the mobile app) and **on the server** since the last synchronization.

### Conflict example

```
Initial state:
┌─────────────────────┬─────────────────────┐
│ Mobile App          │ Server              │
├─────────────────────┼─────────────────────┤
│ Task #1             │ Task #1             │
│ title: "Buy"        │ title: "Buy"        │
│ updated: 10:00      │ updated: 10:00      │
└─────────────────────┴─────────────────────┘

Offline mode — user edits on mobile:
┌─────────────────────┬─────────────────────┐
│ Mobile App          │ Server              │
├─────────────────────┼─────────────────────┤
│ Task #1             │ Task #1             │
│ title: "Buy 🍎"     │ title: "Buy"        │
│ updated: 10:30      │ updated: 10:00      │
└─────────────────────┴─────────────────────┘

Meanwhile, a colleague edits on the server:
┌─────────────────────┬─────────────────────┐
│ Mobile App          │ Server              │
├─────────────────────┼─────────────────────┤
│ Task #1             │ Task #1             │
│ title: "Buy 🍎"     │ title: "Buy 🍞"     │
│ updated: 10:30      │ updated: 10:25      │
└─────────────────────┴─────────────────────┘

❌ CONFLICT on sync!
Which version to keep? 🍎 or 🍞?
```

---

## 🎯 The 4 Strategies

OfflineSync provides 4 ready-to-use strategies:

### 1. Server Wins

**Behaviour:** Server data **always overwrites** local data.

**Advantages:**
- ✅ Simple and predictable
- ✅ Server remains the source of truth
- ✅ No server data loss

**Disadvantages:**
- ❌ Local changes are lost
- ❌ Potentially frustrating for the user

**When to use:**
- Critical data (finances, inventory)
- System information (global settings)
- Important shared data

**Example:**

```php
'conflict_resolution' => [
    'per_resource' => [
        'users'     => 'server_wins',  // User profiles
        'prices'    => 'server_wins',  // Product prices
        'inventory' => 'server_wins',  // Stock levels
    ],
],
```

**Conflict result:**
```
Before sync: Mobile = "Buy 🍎" / Server = "Buy 🍞"
After sync:  Mobile = "Buy 🍞" / Server = "Buy 🍞"
✅ Server won
```

---

### 2. Client Wins

**Behaviour:** Local data **always overwrites** server data.

**Advantages:**
- ✅ Local changes are always preserved
- ✅ Great UX for the end user
- ✅ Ideal for personal preferences

**Disadvantages:**
- ❌ May overwrite important server data
- ❌ Risk of conflicts with other users

**When to use:**
- Personal user preferences
- Local settings
- Drafts and private notes

**Example:**

```php
'conflict_resolution' => [
    'per_resource' => [
        'settings'    => 'client_wins', // Personal settings
        'preferences' => 'client_wins', // UI preferences
        'drafts'      => 'client_wins', // Drafts
    ],
],
```

**Conflict result:**
```
Before sync: Mobile = "Buy 🍎" / Server = "Buy 🍞"
After sync:  Mobile = "Buy 🍎" / Server = "Buy 🍎"
✅ Client won
```

---

### 3. Last Write Wins

**Behaviour:** The version with the **most recent timestamp** wins.

**Advantages:**
- ✅ Logical and fair
- ✅ Fact-based (timestamp)
- ✅ Good general-purpose compromise

**Disadvantages:**
- ⚠️ Depends on system clock accuracy
- ❌ Unpredictable results if clocks are out of sync

**When to use:**
- **Recommended default** for most cases
- Collaborative data
- Shared documents

**Example:**

```php
'conflict_resolution' => [
    'default_strategy' => 'last_write_wins', // Default
    'per_resource' => [
        'tasks'    => 'last_write_wins',
        'projects' => 'last_write_wins',
    ],
],
```

**Conflict result:**
```
Before sync:
  Mobile = "Buy 🍎" (updated: 10:30)
  Server = "Buy 🍞" (updated: 10:25)

After sync:
  Mobile = "Buy 🍎" / Server = "Buy 🍎"
✅ Mobile won (more recent)
```

**Reverse case:**
```
Before sync:
  Mobile = "Buy 🍎" (updated: 10:20)
  Server = "Buy 🍞" (updated: 10:25)

After sync:
  Mobile = "Buy 🍞" / Server = "Buy 🍞"
✅ Server won (more recent)
```

---

### 4. Merge

**Behaviour:** Merges fields from both versions, preferring **non-null values**.

**Advantages:**
- ✅ No data lost
- ✅ Combines the best of both
- ✅ Ideal for objects with many fields

**Disadvantages:**
- ⚠️ May create logical inconsistencies
- ❌ More complex to reason about

**When to use:**
- Product records with many fields
- Complete user profiles
- Structured documents

**Example:**

```php
'conflict_resolution' => [
    'per_resource' => [
        'products' => 'merge', // Product records
        'profiles' => 'merge', // User profiles
    ],
],
```

**Conflict result:**
```
Before sync:
Mobile = {
  title: "Buy 🍎",
  description: "Red apples",
  quantity: null,
  priority: "high"
}

Server = {
  title: "Buy 🍞",
  description: null,
  quantity: 3,
  priority: "medium"
}

After sync (merge):
{
  title: "Buy 🍞",          // Server (base)
  description: "Red apples", // Mobile (non-null)
  quantity: 3,               // Server (non-null)
  priority: "high"           // Mobile (non-null override)
}
✅ Both versions merged
```

---

## ⚙️ Configuration

### Global configuration

**config/offline-sync.php:**

```php
return [
    'conflict_resolution' => [
        // Default strategy for all resources
        'default_strategy' => 'last_write_wins',

        // Per-resource strategies
        'per_resource' => [
            // Critical data → Server Wins
            'users'     => 'server_wins',
            'prices'    => 'server_wins',
            'inventory' => 'server_wins',

            // Personal preferences → Client Wins
            'settings'    => 'client_wins',
            'preferences' => 'client_wins',

            // Collaborative → Last Write Wins
            'tasks'    => 'last_write_wins',
            'projects' => 'last_write_wins',

            // Complex data → Merge
            'products' => 'merge',
            'profiles' => 'merge',
        ],
    ],
];
```

### Dynamic change

```php
use Techparse\OfflineSync\Facades\OfflineSync;

// Change strategy on the fly
config(['offline-sync.conflict_resolution.per_resource.tasks' => 'client_wins']);

// Sync
OfflineSync::sync(['tasks']);
```

---

## 💼 Use Cases

### Case 1: Note-taking app

**Need:** The user never wants to lose local changes.

**Solution:**

```php
'per_resource' => [
    'notes'  => 'client_wins',
    'drafts' => 'client_wins',
],
```

### Case 2: Inventory management app

**Need:** Server inventory is the absolute source of truth.

**Solution:**

```php
'per_resource' => [
    'inventory'   => 'server_wins',
    'stock_levels' => 'server_wins',
],
```

### Case 3: Collaborative app (Trello-like)

**Need:** Multiple users edit the same tasks.

**Solution:**

```php
'per_resource' => [
    'tasks'    => 'last_write_wins',
    'boards'   => 'last_write_wins',
    'comments' => 'client_wins', // Comments don't overwrite each other
],
```

### Case 4: E-commerce with product records

**Need:** Combine server prices with personal local notes.

**Solution:**

```php
'per_resource' => [
    'products' => 'merge',
],
```

**Example:**
```
Mobile edits:
{ id: 1, personal_note: "To buy" }

Server edits:
{ id: 1, price: 29.99 }

Merge result:
{ id: 1, price: 29.99, personal_note: "To buy" }
```

---

## 🔧 Custom Strategies

You can create your own resolution strategies.

### 1. Create a strategy

```php
<?php

namespace App\Sync\Strategies;

use Techparse\OfflineSync\Contracts\SyncStrategy;

class PriorityStrategy implements SyncStrategy
{
    public function resolve(array $conflict): array
    {
        $local  = $conflict['local_data'];
        $remote = $conflict['remote_data'];

        // Win based on priority
        if (($local['priority'] ?? 0) > ($remote['priority'] ?? 0)) {
            return [
                'data'   => $local,
                'winner' => 'client',
                'action' => 'force_push',
                'reason' => 'higher_priority',
            ];
        }

        return [
            'data'   => $remote,
            'winner' => 'server',
            'action' => 'overwrite_local',
            'reason' => 'lower_priority',
        ];
    }

    public function name(): string
    {
        return 'priority';
    }
}
```

### 2. Register the strategy

**app/Providers/AppServiceProvider.php:**

```php
use Techparse\OfflineSync\ConflictResolver;

public function boot()
{
    $this->app->bind('sync.strategy.priority', function () {
        return new \App\Sync\Strategies\PriorityStrategy();
    });
}
```

### 3. Use the strategy

```php
// In config
'per_resource' => [
    'tasks' => 'priority', // Your custom strategy
],
```

---

## 🐛 Debugging

### Listen to conflict events

```php
use Techparse\OfflineSync\Events\ConflictDetected;

Event::listen(ConflictDetected::class, function ($event) {
    Log::warning('Conflict detected', [
        'resource'    => $event->conflict['resource'],
        'resource_id' => $event->conflict['resource_id'],
        'local'       => $event->conflict['local_data'],
        'remote'      => $event->conflict['remote_data'],
    ]);
});
```

### Log resolutions

```php
// In ConflictResolver
$result = $strategy->resolve($conflict);

Log::info('Conflict resolved', [
    'strategy' => $strategy->name(),
    'winner'   => $result['winner'],
    'resource' => $conflict['resource'],
]);
```

### Display in the UI

```php
// Get conflicts after sync
$result = OfflineSync::sync(['tasks']);

if (!empty($result['conflicts'])) {
    foreach ($result['conflicts'] as $conflict) {
        session()->flash('warning', "Conflict on {$conflict['resource']}");
    }
}
```

---

## 📊 Comparison Table

| Strategy | Preserves Local | Preserves Server | Complexity | Use Case |
|----------|-----------------|------------------|------------|----------|
| **server_wins** | ❌ | ✅ | ⭐ Simple | Critical data |
| **client_wins** | ✅ | ❌ | ⭐ Simple | Personal preferences |
| **last_write_wins** | ⚖️ By timestamp | ⚖️ By timestamp | ⭐⭐ Medium | Collaborative |
| **merge** | ✅ Partial | ✅ Partial | ⭐⭐⭐ Complex | Rich records |

---

## 🎯 Recommendations

### By app type

| App Type | Recommendation |
|----------|----------------|
| **Personal notes** | `client_wins` everywhere |
| **E-commerce** | `server_wins` (prices, stock), `merge` (products) |
| **CRM** | `last_write_wins` (contacts), `server_wins` (quotas) |
| **Task management** | `last_write_wins` by default |
| **Inventory** | `server_wins` everywhere |

### Best Practices

1. ✅ **Use `last_write_wins` as default** — good all-round compromise
2. ✅ **`server_wins` for critical data** — finances, stock
3. ✅ **`client_wins` for preferences** — UI, settings
4. ✅ **`merge` with care** — verify business logic
5. ✅ **Log all conflicts** — for analysis and debugging
6. ✅ **Test with close timestamps** — important edge case

---

## ❓ FAQ

**Q: What happens if timestamps are equal?**
A: `last_write_wins` favours the server by default.

**Q: Can multiple strategies be combined?**
A: Yes, via a custom strategy that delegates per field.

**Q: How to avoid conflicts?**
A: Sync frequently, use optimistic UI, apply optimistic locking.

**Q: Do conflicts block the sync?**
A: No, other items continue. Conflicts are reported but do not halt processing.

---

## 📞 Support

Questions about conflicts?
- 📧 Email: offlinessync@techparse.fr
- 📖 Documentation: https://docs.offlinesync.techparse.fr

---

**Master your conflicts!** 🎯
