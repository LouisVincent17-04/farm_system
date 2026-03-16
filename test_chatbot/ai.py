from fastapi import FastAPI
from pydantic import BaseModel

app = FastAPI()

# request structure
class Message(BaseModel):
    text: str

@app.post("/hello")
def hello(data: Message):
    return {"result": "Hello " + data.text}