# Agent Startup Prompt

Use this prompt at the beginning of a session to ensure the agent follows the project's established workflows and utilizes specialized tools correctly.

---

Follow the protocol in AGENTS.md. Start by picking the highest-priority task using 'bd ready --json'. 

Once selected:
1. **Update status**: `bd update <id> --status in_progress`.
2. **Initialize Mail**: Register as `cursor-agent` and reserve relevant files using `file_reservation_paths` (ttl=3600, exclusive=true, reason="bead-<id>"). Use the bead ID as your `thread_id` for all communications.
3. **Execute**: Work the task, verify with Pest tests, and run `vendor/bin/pint --dirty`.
4. **Wrap up**: Release file reservations and `bd close <id> --reason "..."`.

Always use `bv` to visualize the task graph if blockers arise.

