Here’s an extraction of the **prompt templates** and **techniques** used in the agent application in `Cormanz/smartgpt`:

---

### Prompt Templates

Prompt templates are defined in `src/auto/agents/prompt/`, primarily in `adept.rs` and `methodical.rs`. The core struct
is:

```rust
pub struct Prompt<'a, T : Serialize>(pub &'a str, pub PhantomData<T>);
```

The `.fill()` method replaces placeholders (e.g., `[task]`, `[assets]`) with actual values.

**Key Prompt Templates:**

#### In `adept.rs`:

- **PERSONALITY**  
  `Personality: [personality]`

- **CONCISE_PLAN**
  ```
  This is your task:
  [task]
  Make a concise, one-sentence plan on you can complete this task.
  Remember that you have access to external tools, so you can do any task.
  Respond in this JSON format:
  {
    "concise plan on how you will complete the task": "plan"
  }
  ```

- **THOUGHTS**  
  Guides the agent to spawn subtasks, using thoughts, reasoning, and self-criticism.

- **Decision Prompt (for spawning agents, brainstorming, final response):**
  ```
  Focus on using thoughts, reasoning, and self-criticism to complete your goals.

  You make a decision. Here are the types of decisions alongside their `args` schema:

  spawn_agent { "subtask": "...", "assets": [ ... ], "desired_response": "..." }
  brainstorm { "lines": [ ... ] }
  final_response { "response": "..." }

  Assets:
  [assets]

  As you have no assets, you must pass "assets" as [] when spawning an agent.

  Ensure you adhere to your plan:
  [plan]

  Respond in this exact JSON format exactly, with every field in order:
  {
    "thoughts": "...",
    "reasoning": "...",
    "decision": {
      "type": "decision type",
      "args": "..."
    }
  }
  ```

- **NEW_THOUGHTS**
  ```
  Your previous request gave back the response:
  [response]
  You may now make another decision, either `spawn_agent`, `brainstorm`, or `final_response`.
  Try to use `thoughts` to think about what your previous response gave you, your long-term ideas, and where to go next.
  Assets: 
  [assets]

  {
    "thoughts": "...",
    "reasoning": "...",
    "decision": {
      "type": "decision type",
      "args": "..."
    }
  }
  ```

#### In `methodical.rs`:

- **SUMMARIZE_MEMORIES**
  ```
  Please summarize all important actions you took out.
  Please also summarize all observations of information you have collected.
  Be concise.

  Respond in this JSON format:
  {
    "actions": [
      "what tool you used and why"
    ],
    "observations": [
      "what you learned"
    ]
  }
  ```

- **CREATE_PLAN**
  ```
  [tools]

  You have been given these resources and actions.
  You may use these resources and actions, and only these.

  Here is your new task:
  [task]

  Here is a list of your memories:
  [observations]

  Here is a list of assets previously saved:
  [assets]

  Create a list of steps of what you need to do and which resource or action you will use.
  ```

- **NEXT_STEP**
  ```
  Now you will carry out the next step: 
  [step]

  You must carry out this step with one entire action.
  Include ALL information.

  Ensure you don't hallucinate; only give information that you actually have.

  Assets:
  No assets.

  Respond in this JSON format:
  {
    "thoughts": "...",
    "action": {
      "tool": "...",
      "args": { ... }
    }
  }
  ```

- **SAVE_ASSET**
  ```
  Now, you will write this asset:
  [asset]

  Respond in pure plaintext format with a detailed markdown response.
  Include all necessary details as the description stated, alongside any necessary sources or explanation of where you got the information.
  ```

---

### Techniques Used in the Agent Application

- **Plan and Execute**:  
  Agents plan out a task using the `CREATE_PLAN` template, then execute each step one by one using `NEXT_STEP`, saving
  assets and updating memory as they go.

- **Self-Reflection and Criticism**:  
  Prompts encourage the agent to use "thoughts, reasoning, and self-criticism" before acting, aiming for more robust and
  reliable outputs.

- **Structured Decision-Making**:  
  Decisions are always output in a specific JSON schema, including thoughts, reasoning, and a decision type (
  spawn_agent, brainstorm, final_response).

- **Delegation via Sub-Agents**:  
  The `spawn_agent` action allows the dynamic agent to create subtasks for static agents, enabling complex task
  decomposition.

- **Memory Handling**:  
  After a task, the agent summarizes important actions and observations and stores them in long-term memory, retrieved
  for future relevant tasks.

- **Plugin/Tool Integration**:  
  The system supports plugins/tools (like google_search, browse_url), and agents plan their actions around available
  tools.

- **Asset Management**:  
  Agents can save and reference "assets" (intermediate results or data) to be used in subsequent steps or subtasks.

---

**Summary:**  
SmartGPT uses structured, fillable prompt templates to guide agents through planning, execution, self-reflection, memory
management, and asset handling. The approach is modular, JSON-driven, and designed for extensibility with plugins and
tools.