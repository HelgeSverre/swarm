# Prompting Techniques & Agentic Systems Reference

A comprehensive reference guide for developers building agentic systems, covering prompting techniques, applications, and implementation patterns.

## Table of Contents

1. [Core Prompting Techniques](#core-prompting-techniques)
2. [Advanced Prompting Techniques](#advanced-prompting-techniques)
3. [Applications](#applications)
4. [Agentic Systems & Architectures](#agentic-systems--architectures)

---

## Core Prompting Techniques

### Zero-Shot Prompting
**What**: Direct task instructions without examples  
**When**: Simple tasks, broad model capabilities  
**How**: Provide clear instruction → Get response  
**Example**:
```
Classify the text into neutral, negative or positive.
Text: I think the vacation is okay.
```
**Result**: "Neutral"

### Few-Shot Prompting
**What**: In-context learning with 1-3 examples  
**When**: Complex tasks needing demonstration  
**How**: Show examples → Model learns pattern → Apply to new input  
**Example**:
```
A "whatpu" is a small, furry animal native to Tanzania. 
An example of a sentence that uses the word whatpu is:
We were traveling in Africa and we saw these very cute whatpus.
```
**Key**: Consistent format, representative examples

### Chain-of-Thought (CoT)
**What**: Step-by-step reasoning demonstrations  
**When**: Multi-step problems, mathematical reasoning  
**How**: Show reasoning steps → Model follows pattern  
**Example**:
```
Problem: The odd numbers in this group add up to an even number: 15, 32, 5, 13, 82, 7, 1. 
Reasoning: Add all odd numbers: 15 + 5 + 13 + 7 + 1 = 41. 41 is odd, so NO.
```
**Tip**: "Let's think step by step" can trigger CoT reasoning

### Self-Consistency
**What**: Multiple reasoning paths with majority voting  
**When**: Complex reasoning tasks  
**How**: Generate multiple solutions → Select most consistent answer  
**Example**: For "When I was 6 my sister was half my age. Now I'm 70 how old is my sister?" - generate 3 solutions, pick majority answer (67)  
**Benefit**: Reduces errors from single reasoning path

### Generated Knowledge
**What**: Generate relevant context before answering  
**When**: Knowledge-intensive tasks  
**How**: Generate knowledge → Use knowledge to answer  
**Example**:
```
1. Generate: "Golf scoring: lower scores are better..."
2. Answer: "Part of golf is NOT trying to get a higher point total"
```

### Prompt Chaining
**What**: Sequential prompts building on each other  
**When**: Complex multi-stage tasks  
**How**: Task 1 output → Task 2 input → Final result  
**Example**:
```
1. Extract quotes from document
2. Use quotes to compose answer
```
**Benefit**: Debuggable, controllable stages

---

## Advanced Prompting Techniques

### Tree of Thoughts (ToT)
**What**: Explore multiple reasoning paths systematically  
**When**: Strategic reasoning, complex problem-solving  
**How**: Generate thoughts → Evaluate → Search (BFS/DFS) → Backtrack if needed  
**Example**: Game of 24 - explore equation combinations, evaluate feasibility  
**Key**: Define evaluation criteria, search depth

### Retrieval Augmented Generation (RAG)
**What**: Combine retrieval with generation  
**When**: Knowledge-intensive, factual tasks  
**How**: Query → Retrieve documents → Concatenate → Generate  
**Example**: Retrieve Wikipedia articles → Generate accurate summaries  
**Benefit**: Reduces hallucination, enables up-to-date info

### ReAct (Reasoning + Acting)
**What**: Interleave reasoning with actions  
**When**: Tasks needing external tools/information  
**How**: Thought → Action → Observation → Repeat  
**Example**:
```
Thought: I need to search for Colorado orogeny
Action: Search["Colorado orogeny"]
Observation: [search results]
Thought: Based on results...
```
**Benefits**:
- Reduces hallucinations through fact-checking
- Provides transparent reasoning traces
- Enables multi-step problem solving
- Allows dynamic adaptation during execution
**Critical**: Foundation for modern AI agents

### Program-Aided Language Models (PAL)
**What**: Generate code to solve problems  
**When**: Computational tasks, precise calculations  
**How**: Problem → Generate code → Execute → Result  
**Example**:
```python
# Question: Born 25 years ago from Feb 27, 2023
from datetime import datetime, timedelta
birth_date = datetime(2023, 2, 27) - timedelta(days=25*365.25)
print(birth_date.strftime("%m/%d/%Y"))  # 02/27/1998
```

### Automatic Prompt Engineer (APE)
**What**: Automated prompt optimization  
**When**: Need optimal prompts for specific tasks  
**How**: Generate candidates → Test → Select best  
**Discovery**: "Let's work this out step by step to be sure we have the right answer"  
**Benefit**: Often finds better prompts than humans

### Active-Prompt
**What**: Dynamic example selection based on uncertainty  
**When**: Task-specific optimization needed  
**How**: Query LLM → Find uncertain cases → Annotate → Use as examples  
**Process**: Uncertainty = disagreement in multiple answers

### Reflexion
**What**: Learning from mistakes through self-reflection  
**When**: Iterative improvement tasks  
**How**: Act → Evaluate → Reflect → Store experience → Retry  
**Components**: Actor, Evaluator, Self-Reflection models  
**Example**: Navigation tasks improving through reflection

### Multimodal CoT
**What**: Reasoning across text and images  
**When**: Problems requiring visual understanding  
**How**: Stage 1: Generate rationale → Stage 2: Infer answer  
**Performance**: 1B model can outperform GPT-3.5 on science tasks

---

## Applications

### Fine-Tuning GPT-4o
**Purpose**: Customize models for specific tasks  
**Example**: Emotion classification  
**Process**: Prepare JSONL data → Train → Deploy  
**Cost**: $25/M training tokens, $3.75/M inference  
**Tip**: 1M free training tokens/day promotion

### Function Calling
**Purpose**: Connect LLMs to external tools  
**Implementation**:
```json
{
  "name": "get_weather",
  "description": "Get current weather",
  "parameters": {...}
}
```
**Flow**: User query → Extract parameters → Call function → Natural response

### Context Caching (Gemini)
**Purpose**: Efficient large document querying  
**How**: Upload once → Cache → Query multiple times  
**Example**: Year of ML papers → Query without re-uploading  
**Benefit**: Reduces token usage, faster responses

### Synthetic Data Generation
**Basic**: Generate labeled examples  
```
"Generate 10 sentiment examples (8 positive, 2 negative)"
```

**RAG Training (PROMPTGATOR)**:
- Create 4-8 manual examples
- Generate queries for documents
- Fine-tune retrieval models
- Cost: ~$55 for 50k examples

**Diverse Datasets**:
- Randomize parameters (vocabulary, features)
- Use high temperature
- Hierarchical generation

### Code Generation
**Techniques**:
1. **Direct**: "Write a program that..."
2. **Comments-to-Code**: Transform comments
3. **Completion**: Finish partial functions
4. **Query Generation**: Natural language → SQL

**Best Practice**: Always review, test, check imports

### Prompt Optimization Case Study
**Task**: Job classification for graduates  
**Improvement**: 65.6% → 91.7% F1 score  
**Key Changes**:
- Add role instructions
- Use system/user messages
- Reiterate key points
- Give model a name
- Positive reinforcement

**Insight**: "Properly giving instructions and repeating key points is the biggest driver"

### Prompt Functions
**Concept**: Encapsulate prompts as reusable functions  
**Template**:
```
function_name: translate_text
input: ["text"]
rule: "Translate to English, preserve tone"
```
**Benefit**: Modular, chainable AI workflows

---

## Agentic Systems & Architectures

### Core Agent Types

#### Simple Agents
- Single-turn responses
- No tool usage
- Direct generation

#### ReAct Agents
- Reasoning + Acting loops
- Tool integration
- Multi-step problem solving
- Format: Thought → Action → Observation → Repeat

### Orchestration Patterns

#### Linear Orchestrator
**What**: Sequential, predetermined workflow  
**When**: Predictable processes  
**Example**: Data pipeline, report generation

#### Adaptive Orchestrator
**What**: Dynamic routing based on context  
**When**: Complex, evolving queries  
**Feature**: Real-time adjustments

#### Graph Orchestrator
**What**: Customizable state machines  
**Features**:
- Conditional routing
- Multiple tasks per state
- Internal context tracking
- Flexible input handling
**Example**:
```python
def routing_function(input_data) -> Literal['END', 'refinement']:
    if input_data.get('correct', False):
        return 'END'
    return 'refinement'
```
**Use Case**: Complex workflows with branching logic

### Implementation Patterns

#### ReAct Implementation
```python
# Core loop structure
while not done:
    thought = generate_thought(context)
    action = decide_action(thought)
    observation = execute_action(action)
    context.update(thought, action, observation)

# Detailed ReAct format example
Thought: "I need to search for information about X"
Action: WebSearch
Action Input: {"query": "information about X"}
Observation: "Search results show..."
Thought: "Based on the results, I need to..."
# Loop continues until task completion
```

#### Tool Integration
- Strict JSON formats
- Error handling
- Timeout mechanisms
- Result caching
- Maintain conversation context across iterations
- Allow multiple tool calls before final answer

#### Memory Systems
- Conversation history
- Semantic search
- Context retention
- State management
- Optional but recommended for multi-turn conversations

### Advanced Capabilities

#### Multimodal Support
- Text + vision processing
- Document understanding
- Image generation

#### Agent Collaboration
- Handoff mechanisms
- Specialized agents
- Multi-agent workflows

#### Built-in Tools
- Web search
- Code execution
- Document libraries
- Citation tracking

### Best Practices

1. **Loop Limits**: Set max iterations for ReAct agents
2. **Clear Roles**: Define agent purposes explicitly
3. **Structured Output**: Enforce JSON formats for reliability
4. **Logging**: Comprehensive debugging at each step
5. **Context Management**: Limit to relevant information
6. **Caching**: Store tool results for performance
7. **Timeouts**: Prevent infinite loops with limits
8. **Error Handling**: Graceful failures with fallbacks
9. **Prompt Design**: Include clear reasoning steps
10. **Tool Patterns**: Define strict interaction formats
11. **Memory Usage**: Balance history vs token limits
12. **Testing**: Validate edge cases and error paths

### Architecture Components

1. **Language Model**: Core reasoning engine
2. **Tool Registry**: Available actions
3. **Control Loop**: Manages cycles
4. **Context Manager**: State tracking
5. **Output Parser**: Structured responses

### Performance Optimization

- Stream responses for real-time feedback
- Cache frequently used tool results
- Limit context window size
- Implement parallel tool calls
- Use appropriate model sizes

### Common Patterns

#### Research Agent
```
1. Understand query
2. Search multiple sources
3. Synthesize findings
4. Generate report
```

#### Code Assistant
```
1. Analyze requirements
2. Generate code
3. Test/validate
4. Refine based on errors
```

#### Data Analyst
```
1. Load data
2. Explore patterns
3. Generate insights
4. Create visualizations
```

### Domain-Specific Use Cases

#### Customer Service
- **Implementation**: ReAct agents with tool access
- **Features**: FAQ retrieval, ticket management, escalation
- **Example**: Complex query handling with knowledge base search

#### Financial Analysis
- **Implementation**: Multi-agent with specialized roles
- **Features**: Market data retrieval, calculation tools, report generation
- **Example**: Investment research combining multiple data sources

#### Medical Diagnosis Support
- **Implementation**: RAG + ReAct for knowledge retrieval
- **Features**: Symptom analysis, medical literature search, decision trees
- **Example**: Differential diagnosis with reasoning traces

#### Education & Tutoring
- **Implementation**: Adaptive orchestrator with memory
- **Features**: Personalized learning paths, progress tracking, explanations
- **Example**: Interactive problem-solving with step-by-step guidance

#### Software Development
- **Implementation**: Code generation + testing agents
- **Features**: Context-aware completion, error diagnosis, refactoring
- **Example**: Full-stack feature implementation with tests

---

## Quick Reference

### Choosing Techniques

| Task Type | Recommended Technique |
|-----------|----------------------|
| Simple classification | Zero-shot |
| Complex with examples | Few-shot |
| Multi-step reasoning | Chain-of-Thought |
| Need accuracy | Self-Consistency |
| Knowledge-intensive | RAG or Generated Knowledge |
| Tool usage | ReAct |
| Calculations | PAL |
| Iterative improvement | Reflexion |

### Cost Considerations

| Technique | Relative Cost | Why |
|-----------|--------------|-----|
| Zero-shot | Low | Single call |
| Few-shot | Low-Medium | Longer prompts |
| CoT | Medium | Reasoning tokens |
| Self-Consistency | High | Multiple generations |
| ToT | Very High | Many evaluations |
| ReAct | Variable | Depends on tool calls |

### Implementation Checklist

- [ ] Define clear task requirements
- [ ] Choose appropriate prompting technique
- [ ] Design agent architecture (if needed)
- [ ] Implement tool integrations
- [ ] Add error handling
- [ ] Set up logging/monitoring
- [ ] Test edge cases
- [ ] Optimize for performance
- [ ] Document usage patterns
- [ ] Consider computational costs
- [ ] Plan for scaling

### Challenges & Future Considerations

#### Current Challenges
- Computational complexity with deep reasoning chains
- Maintaining coherent state across long conversations
- Balancing autonomy with control
- Ensuring reliable tool execution
- Managing token limits effectively

#### Emerging Patterns
- Graph-based orchestration for complex workflows
- Adaptive routing based on execution history
- Multi-agent collaboration with specialized roles
- Integration with external knowledge bases
- Self-improving agents through reflection

#### Future Directions
- More sophisticated memory systems
- Better multi-modal integration
- Improved efficiency in reasoning
- Enhanced collaboration patterns
- Automated agent optimization

---

*This reference combines insights from promptingguide.ai and practical agent implementation patterns for building production-ready agentic systems.*