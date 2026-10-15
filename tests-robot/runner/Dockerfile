# Executor isolado dos testes: Python + Robot Framework + Browser (Playwright/Chromium).
# Imagem oficial da Browser library — já traz Node.js e os navegadores instalados.
FROM marketsquare/robotframework-browser:20.5.0

WORKDIR /robot
COPY --chown=pwuser:pwuser requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt
COPY --chown=pwuser:pwuser . .
RUN mkdir -p /robot/results

ENV ROBOT_RESULTS_DIR=/robot/results \
    PYTHONUNBUFFERED=1
EXPOSE 8270
CMD ["python3", "runner/server.py"]
