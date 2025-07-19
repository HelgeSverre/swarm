## Inspiration and Reference code on other similar AI Agent tools.

- https://ghuntley.com/amazon-kiro-source-code/
- https://github.com/ghuntley/claude-code-source-code-deobfuscation/blob/main/specs/architecture.md
- https://github.com/ghuntley/claude-code-source-code-deobfuscation/blob/main/claude-code/src/ai/prompts.ts
- https://github.com/ghuntley/claude-code-source-code-deobfuscation
- https://ghuntley.com/tradecraft/
- https://www.reidbarber.com/blog/reverse-engineering-claude-code
- https://github.com/openai/codex
- https://cdn.openai.com/business-guides-and-resources/a-practical-guide-to-building-agents.pdf (pdf)
- https://platform.openai.com/docs/guides/structured-outputs
- https://platform.openai.com/docs/guides/function-calling?api-mode=chat
- https://www.promptingguide.ai/techniques/cot
- https://www.promptingguide.ai/techniques/react
- https://www.promptingguide.ai/techniques/tot
- https://python.langchain.com/docs/integrations/tools/

----

# Literature on AI Agents and LLMs

- https://docs.swarms.world/en/latest/examples/paper_implementations/
- https://docs.swarms.world/en/latest/examples/
- https://arize.com/ai-agents/
- https://arize.com/ai-product-manager/
- https://apphp.gitbook.io/artificial-intelligence-with-php/llms.txt
- https://dev.to/jamesli/react-vs-plan-and-execute-a-practical-comparison-of-llm-agent-patterns-4gh9
- https://dev.to/jamesli/langgraph-state-machines-managing-complex-agent-task-flows-in-production-36f4
- https://dev.to/jamesli/agent-task-orchestration-system-from-design-to-production-1kof
- https://developers.googleblog.com/en/a2a-a-new-era-of-agent-interoperability/
- https://dev.to/callebknox/agent-architectures-that-scale-53of

----

# Other interesting stuff.

- https://www.psychologytoday.com/us/blog/finding-purpose/201902/what-actually-is-a-thought-and-how-is-information-physical
- https://www.phparch.com/2024/01/creating-finite-state-machines-in-php-8-3/
- https://doc.akka.io/libraries/akka-core/current/typed/guide/actors-motivation.html
- https://medium.com/@m.elqrwash/understanding-the-actor-design-pattern-a-practical-guide-to-building-actor-systems-with-akka-in-9ffda751deba
- https://www.microsoft.com/en-us/research/wp-content/uploads/2016/02/Orleans-MSR-TR-2014-41.pdf

----

# Async PHP Stuff

- https://github.com/swoole/awesome-swoole
- https://github.com/thgs/awesome-amphp
- https://php.watch/versions/8.1/fibers
- https://framework-x.org/docs/getting-started/quickstart/
- https://dev.to/jackmarchant/exploring-async-php-5b68

----

# Useful, but more "Abstract" Concepts/Ideas.

## Model Context Protocol (MCP)

https://modelcontextprotocol.io/docs/concepts/architecture

MCP (Model Context Protocol) is a standard way for AI applications and agents to connect to and work with your data
sources (e.g. local files, databases, or content repositories) and tools (e.g. GitHub, Google Maps, or Puppeteer).

## DAG - Task Model (Directed Acyclic Graph, aka Task object with all the necessary things to do the task)

> A DAG is a model that encapsulates everything needed to execute a workflow. Some DAG attributes include the following:
> - Schedule: When the workflow should run.
> - Tasks: tasks are discrete units of work that are run on workers.
> - Task Dependencies: The order and conditions under which tasks execute.
> - Callbacks: Actions to take when the entire workflow completes.
> - Additional Parameters: And many other operational details.
>
> https://airflow.apache.org/docs/apache-airflow/stable/core-concepts/dags.html
>
> _The term “DAG” comes from the mathematical concept “directed acyclic graph”, but the meaning in Airflow has evolved
well beyond just the literal data structure associated with the mathematical DAG concept._

## Database Diagram for Apache Airflow (Task orchestration) system)

> Apache Airflow® is a platform created by the community to programmatically author, schedule and monitor workflows.

- Image: https://airflow.apache.org/docs/apache-airflow/stable/_images/airflow_erd.svg
- Web: https://airflow.apache.org/docs/apache-airflow/stable/database-erd-ref.html