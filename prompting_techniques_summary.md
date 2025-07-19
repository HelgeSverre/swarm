# Prompting Techniques Summary

A comprehensive guide to prompting techniques from promptingguide.ai

## 1. Zero-Shot Prompting

**Description**: A prompting technique where large language models perform tasks without being given specific examples, relying on their broad training to understand and execute instructions directly.

**How it works**:
- Leverages large-scale model training and instruction tuning
- Provides a direct task instruction without demonstrative examples
- Relies on the model's inherent understanding of language and tasks

**When to use**:
- When the language model has broad capabilities
- For straightforward tasks that don't require complex context
- With models like GPT-3.5 Turbo, GPT-4, and Claude 3

**Example**:
```
Classify the text into neutral, negative or positive. 
Text: I think the vacation is okay.
Sentiment:
```
Result: "Neutral"

**Key considerations**:
- Effectiveness depends on model's training and instruction tuning
- May not work for highly specialized or complex tasks
- When zero-shot fails, consider few-shot prompting with examples

## 2. Few-Shot Prompting

**Description**: A prompting technique that enables in-context learning by providing demonstrations in the prompt to help large language models perform better on complex tasks.

**How it works**:
- Provide 1-3 example inputs and outputs in the prompt
- Demonstrate the desired task format and response style
- Use these examples to "condition" the model's subsequent response

**When to use**:
- When zero-shot prompting falls short on more complex tasks
- To improve model performance on specific types of problems
- When you want to guide the model's understanding through examples

**Example**:
```
A 'whatpu' is a small, furry animal native to Tanzania. An example of a sentence that uses 
the word whatpu is: We were traveling in Africa and we saw these very cute whatpus.
```

**Key considerations**:
- Works best when demonstrations represent the true label distribution and maintain consistent format
- Not effective for complex reasoning tasks
- May require more advanced techniques like Chain-of-Thought prompting for intricate problems

## 3. Chain-of-Thought (CoT) Prompting

**Description**: A prompting technique that enables complex reasoning by guiding language models through intermediate reasoning steps.

**How it works**:
- Provide examples that demonstrate step-by-step reasoning
- Show the model how to break down complex problems
- Encourage logical, sequential problem-solving

**When to use**:
- Tasks requiring multi-step reasoning
- Complex mathematical or logical problems
- Scenarios needing detailed analytical thinking

**Example**:
Original problem: Determining if odd numbers in a group add up to an even number
CoT approach: Explicitly showing calculation steps like "Adding all odd numbers (15, 5, 13, 7, 1) gives 41"

**Key considerations**:
- Works best with sufficiently large language models
- Can be implemented with few-shot (multiple examples) or zero-shot techniques
- The "Let's think step by step" prompt can significantly improve reasoning
- This is an "emergent ability" that becomes more effective as models become more sophisticated

## 4. Self-Consistency

**Description**: A prompt engineering technique that aims to improve reasoning by generating multiple solution paths and selecting the most consistent answer.

**How it works**:
- Sample multiple diverse reasoning paths using few-shot Chain-of-Thought prompting
- Generate several solution attempts
- Select the most consistent or majority answer

**When to use**:
- Arithmetic reasoning tasks
- Commonsense reasoning problems
- Tasks requiring complex multi-step reasoning

**Example**:
For the problem "When I was 6 my sister was half my age. Now I'm 70 how old is my sister?", 
the technique generated three different reasoning paths, with two outputs converging on the answer of 67.

**Key considerations**:
- Replaces "naive greedy decoding" in traditional Chain-of-Thought prompting
- Helps boost performance on complex reasoning tasks
- Requires generating multiple solution attempts

## 5. Generated Knowledge Prompting

**Description**: A technique where large language models first generate relevant knowledge about a topic before attempting to answer a specific question or solve a problem.

**How it works**:
1. Generate relevant background knowledge about the input
2. Use the generated knowledge to inform and improve the subsequent answer
3. Potentially increase accuracy and reasoning capabilities

**When to use**:
- For tasks requiring deeper reasoning
- When addressing complex questions that need contextual understanding
- To improve model performance on commonsense reasoning tasks

**Example**:
For a golf-related question, the model first generates knowledge about golf's scoring system, then uses that knowledge to more accurately answer whether "part of golf is trying to get a higher point total than others."

**Key considerations**:
- Model's generated knowledge can vary in confidence and accuracy
- Requires careful integration of generated knowledge with the original query
- Performance may depend on the specific task and model capabilities

## 6. Prompt Chaining

**Description**: Prompt chaining is a technique where complex tasks are broken into subtasks, with each subtask's response used as input for the next prompt in a sequential chain.

**How it works**:
- Split a complex task into multiple subtasks
- Create a series of prompts that transform or process responses
- Each prompt builds upon the output of the previous prompt
- Aim to improve reliability and performance of language models

**When to use**:
- Solving complex tasks that are difficult to address in a single prompt
- Improving transparency and controllability of AI applications
- Building conversational assistants
- Performing multi-step document analysis or question-answering

**Example**:
Document Question Answering:
1. First prompt extracts relevant quotes from a document
2. Second prompt uses those quotes to compose a comprehensive answer
3. Demonstrates how responses can be refined through multiple prompt stages

**Key considerations**:
- Helps break down complex tasks into manageable steps
- Increases model performance and response reliability
- Allows for easier debugging and performance analysis
- May increase computational complexity and response time compared to single-prompt approaches

## 7. Tree of Thoughts (ToT)

**Description**: A prompting technique that enables language models to explore complex problem-solving by maintaining a tree of intermediate reasoning steps, allowing systematic exploration and evaluation of potential solutions.

**How it works**:
- Generates multiple "thoughts" as intermediate reasoning steps
- Uses search algorithms like breadth-first or depth-first search
- Allows self-evaluation of progress toward solving a problem
- Enables lookahead and backtracking through potential solution paths

**When to use**:
- Complex tasks requiring strategic reasoning
- Problems needing exploration and multiple solution attempts
- Scenarios where step-by-step problem-solving is critical

**Example**:
Game of 24 mathematical reasoning task:
- Breaks down problem into 3 steps
- Generates intermediate equations
- Evaluates each candidate thought as "sure/maybe/impossible"
- Keeps best 5 candidates at each step

**Key considerations**:
- Requires defining number of candidate thoughts and steps
- More computationally intensive than simple prompting
- Effectiveness varies by task complexity
- Helps overcome limitations of traditional linear reasoning approaches

## 8. Retrieval Augmented Generation (RAG)

**Description**: RAG combines an information retrieval component with a text generator model to enhance language models' ability to access and use external knowledge.

**How it works**:
- Retrieves relevant supporting documents from an external source (e.g., Wikipedia)
- Concatenates retrieved documents with the original input prompt
- Feeds combined context to a text generator to produce the final output

**When to use**:
- Knowledge-intensive tasks
- Scenarios requiring up-to-date or specialized information
- Tasks where factual consistency is critical

**Example**:
Generating concise machine learning paper titles by retrieving and incorporating relevant research context

**Key considerations**:
- Helps mitigate "hallucination" in language models
- Allows accessing latest information without full model retraining
- Enables more factual, specific, and diverse responses
- Works best with complex, knowledge-dependent tasks
- Depends on quality and comprehensiveness of retrieval source
- Performance tied to effectiveness of retrieval mechanism
- Requires additional computational resources for document retrieval

## 9. Automatic Reasoning and Tool-use (ART)

**Description**: ART is a framework that uses a frozen large language model to automatically generate intermediate reasoning steps as a program, enabling intelligent task decomposition and tool use.

**How it works**:
1. Select multi-step reasoning and tool use demonstrations from a task library
2. At test time, pause generation when external tools are called
3. Integrate tool outputs before resuming generation
4. Encourage zero-shot generalization across tasks

**When to use**:
- Complex tasks requiring multi-step reasoning
- Scenarios needing systematic tool integration
- Tasks where step-by-step problem solving is crucial

**Example**:
While not explicitly provided, the technique could be used for solving complex mathematical problems by breaking them down and using computational tools at each reasoning step.

**Key considerations**:
- Enables zero-shot task generalization
- Extensible through updating task and tool libraries
- Demonstrated performance improvements on benchmarks like BigBench and MMLU
- Requires a robust library of demonstrations and tools
- Depends on quality of task and tool libraries
- Requires careful demonstration selection
- Performance may vary across different task domains

## 10. Automatic Prompt Engineer (APE)

**Description**: A framework for automatic instruction generation and selection that uses large language models to generate and optimize prompts.

**How it works**:
- An LLM generates candidate instruction prompts
- Candidate prompts are executed on a target model
- The most effective prompt is selected based on evaluation scores

**When to use**:
- When seeking to automatically optimize zero-shot prompting
- For improving performance on reasoning and mathematical tasks
- When manual prompt engineering is challenging

**Example**:
APE discovered a better zero-shot Chain-of-Thought prompt: "Let's work this out in a step by step way to be sure we have the right answer." This improved performance on math benchmarks like MultiArith and GSM8K.

**Key considerations**:
- Treats prompt optimization as a "black-box optimization problem"
- Relies on LLMs' ability to generate and evaluate potential prompts
- Part of a broader research area exploring automated prompt engineering
- Requires computational resources for generating and testing multiple prompts
- Performance depends on the capabilities of the underlying language model

## 11. Active-Prompt

**Description**: A prompting approach that adapts large language models to task-specific examples by dynamically selecting and annotating the most uncertain training questions.

**How it works**:
1. Query the LLM with initial CoT examples
2. Generate multiple possible answers for training questions
3. Calculate uncertainty based on answer disagreement
4. Select most uncertain questions for human annotation
5. Use new annotated exemplars to improve inference

**When to use**:
- When fixed chain-of-thought (CoT) examples are not optimally effective
- For improving task-specific reasoning in language models
- When you want to dynamically improve prompt quality

**Example**:
Not explicitly provided in the source document

**Key considerations**:
- Relies on human annotation of selected uncertain questions
- Aims to address limitations of static CoT prompting
- Requires computational resources to generate multiple answers
- Depends on effective uncertainty measurement
- The exemplars might not be the most effective examples for the different tasks before applying Active-Prompt

## 12. Directional Stimulus Prompting (DSP)

**Description**: A prompting technique that uses a tuneable policy language model to generate hints/stimuli that guide a black-box frozen large language model toward generating more desired outputs.

**How it works**:
- A small policy language model is trained to generate strategic hints
- These hints are used to direct the main large language model's response
- Allows more precise control over the LLM's output generation

**When to use**:
- When you want more targeted and controlled responses from a language model
- To improve summary generation or other specific output tasks

**Example**:
Not provided in the source document (noted as "Full example coming soon!")

**Key considerations**:
- Involves using reinforcement learning to optimize the hint generation
- The policy language model can be smaller than the main LLM
- Provides a method to guide a "frozen" (pre-trained) language model
- Information is primarily from Li et al., (2023) research

## 13. Program-Aided Language Models (PAL)

**Description**: A method that uses large language models to generate programs as intermediate reasoning steps, instead of using free-form text to solve problems.

**How it works**:
- LLM reads a natural language problem
- Generates a Python (or other programming language) code snippet
- Uses a runtime interpreter (like Python) to execute the code and solve the problem

**When to use**:
- For problems requiring step-by-step computational reasoning
- When precise calculations or date/time manipulations are needed
- To leverage programmatic logic for solving complex problems

**Example**:
Question: "Today is 27 February 2023. I was born exactly 25 years ago. What is the date I was born in MM/DD/YYYY?"
- PAL generates Python code to calculate the birthdate
- Executes the code to produce the answer: 02/27/1998

**Key considerations**:
- Offloads solution steps to a programmatic runtime
- Differs from chain-of-thought prompting by using code instead of text
- Requires programming language knowledge to implement effectively

## 14. ReAct Prompting

**Description**: ReAct is a framework where LLMs generate both reasoning traces and task-specific actions in an interleaved manner. It allows language models to dynamically reason and interact with external environments.

**How it works**:
- Generates verbal reasoning traces and actions for a task
- Interleaves "thought" and "action" steps
- Enables interaction with external information sources
- Helps models create, maintain, and adjust action plans

**When to use**:
- Knowledge-intensive reasoning tasks
- Question answering
- Decision-making scenarios
- Tasks requiring external information retrieval
- Situations needing dynamic reasoning and exploration

**Example**:
Answering a complex question about Colorado orogeny by:
1. Generating initial search strategy
2. Performing targeted searches
3. Refining search queries
4. Synthesizing final answer based on retrieved information

**Key considerations**:
- Depends heavily on quality of external information retrieved
- Can suffer from non-informative search results
- Performance varies across different task types
- Most effective when combined with chain-of-thought prompting
- Structural constraints can reduce reasoning flexibility
- Potential for derailed reasoning with poor information sources
- Not yet as performant as human experts in complex tasks

## 15. Reflexion

**Description**: Reflexion is a framework to reinforce language-based agents through linguistic feedback that helps agents learn from past mistakes by providing self-reflective context.

**How it works**:
- Uses three key models: Actor, Evaluator, and Self-Reflection
- Actor generates actions and trajectories
- Evaluator scores outputs
- Self-Reflection generates verbal feedback to improve future performance
- Stores experiences in memory to guide future decision-making

**When to use**:
- Tasks requiring learning through trial and error
- Scenarios where traditional reinforcement learning is impractical
- Environments needing nuanced feedback
- Tasks involving:
  - Sequential decision-making
  - Reasoning challenges
  - Programming problems

**Example**:
An AI agent solving complex navigation tasks in AlfWorld, where it learns to improve its path-finding by reflecting on previous unsuccessful attempts.

**Key considerations**:
- Relies on the agent's ability to self-evaluate accurately
- Works best with tasks that allow iterative improvement
- Limited by long-term memory constraints
- Requires sophisticated language models for effective reflection
- Challenges with complex task self-evaluation
- Potential memory storage issues
- Constraints in generating consistently accurate code or solutions

## 16. Multimodal Chain-of-Thought (Multimodal CoT) Prompting

**Description**: A two-stage prompting approach that incorporates both text and vision, moving beyond traditional language-only chain-of-thought reasoning.

**How it works**:
- Stage 1: Generate rationales using multimodal (text and visual) information
- Stage 2: Infer answers based on the generated rationales

**When to use**:
- When solving complex problems that require reasoning across text and visual inputs
- For tasks in domains like science question answering that benefit from multimodal reasoning

**Example**:
The research demonstrated the technique on the ScienceQA benchmark, where the multimodal CoT model (1B) outperforms GPT-3.5

**Key considerations**:
- Integrates visual and textual information for more comprehensive reasoning
- Requires inputs that have both text and visual components
- Potentially more effective for complex reasoning tasks compared to text-only approaches
- The technique is still emerging, and its effectiveness may vary across different domains and problem types

## 17. Graph Prompting

**Description**: A prompting framework for graphs introduced by Liu et al. in 2023, designed to improve performance on downstream tasks.

**How it works**:
The specific technical details are not elaborated in the available documentation.

**When to use**:
Specific use cases are not provided in the current documentation.

**Example**:
No concrete example is provided in the text.

**Key considerations**:
- The documentation appears to be minimal with limited information
- More research would be needed to provide a comprehensive explanation
- The page notes "More coming soon!", indicating the information is preliminary

---

*Note: This summary is based on information available at promptingguide.ai as of the date of extraction. Some techniques may have limited documentation or examples available.*