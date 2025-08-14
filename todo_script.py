# Todo List Manager

todo_list = []


def add_task(task):
    todo_list.append(task)


def remove_task(index):
    if 0 <= index < len(todo_list):
        del todo_list[index]
    else:
        print("Invalid index")


def view_tasks():
    for i, task in enumerate(todo_list):
        print(f"{i + 1}. {task}")


def save_tasks(filename):
    with open(filename, 'w') as f:
        for task in todo_list:
            f.write(task + '\n')

# Example usage:
add_task("Buy groceries")
add_task("Read a book")
view_tasks()
save_tasks("todo.txt")
