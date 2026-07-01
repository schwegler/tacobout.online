Update the interaction badge on posts to correctly sum up all comments and "Fediverse reactions" (like reposts, likes, and quotes from ActivityPub), replacing the previous emoji with a flat SVG icon.

**What:**
- Changed the comment count logic from `get_comments_number()` (which ignores custom comment types) to `get_comments()` with `type=all` and `count=true` to properly include ActivityPub interactions.
- Replaced the hardcoded `💬` emoji with a clean SVG icon.
- Applied these changes to both the PHP backend render function (`functions.php`) and the infinite scroll script (`tacobout-infinite-scroll.js`).

**Why:**
- The ActivityPub plugin registers "Fediverse reactions" as comments using custom types. Using `get_comments_number()` resulted in these interactions being excluded from the badge total.
- The user specifically requested a flat icon instead of an emoji.

**Impact:**
- The interaction badge now displays accurate combined totals of both standard comments and Fediverse reactions.
- The visual style matches the request (SVG instead of emoji).
