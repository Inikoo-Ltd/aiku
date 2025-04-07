import Image from '@tiptap/extension-image';

export const CustomImage = Image.configure({ inline: true }).extend({
  addAttributes() {
    return {
      ...this.parent?.(),
      float: {
        default: 'none',
        renderHTML: ({ float }) => {
          const style = `
            ${float === 'left' ? 'float: left; margin-right: 1rem;' : ''}
            ${float === 'right' ? 'float: right; margin-left: 1rem;' : ''}
            ${float === 'none' ? 'display: block; margin: 0 auto;' : ''}
            width: 100%;
            height: auto;
            cursor: pointer;
            display: inline;
          `;
          return { style: style.trim() };
        },
        parseHTML: element => element.style.float || 'none',
      },
      width: {
        default: null,
        renderHTML: ({ width }) => {
          return width ? { style: `width: ${width};` } : {};
        },
        parseHTML: element => element.style.width || null,
      },
    };
  },

  addNodeView() {
    return ({ node, editor, getPos }) => {
      const { view, options: { editable } } = editor;
      const { float, width } = node.attrs;

      const $wrapper = document.createElement('div');
      const $container = document.createElement('div');
      const $img = document.createElement('img');

      $wrapper.appendChild($container);
      $container.appendChild($img);

      // Apply image attributes
      Object.entries(node.attrs).forEach(([key, value]) => {
        if (value != null && key !== 'width') $img.setAttribute(key, value);
      });

      $img.style.width = '100%';
      $img.style.height = 'auto';
      $img.style.display = 'block';

      const containerStyle = `
        ${float === 'left' ? 'float: left; margin-right: 1rem;' : ''}
        ${float === 'right' ? 'float: right; margin-left: 1rem;' : ''}
        ${float === 'none' ? 'display: block; margin: 0 auto;' : ''}
        cursor: pointer;
        position: relative;
      `.trim();
      $container.setAttribute('style', containerStyle);

      if (width) {
        $container.style.width = width;
      }

      const dispatchNodeView = () => {
        if (typeof getPos === 'function') {
          view.dispatch(view.state.tr.setNodeMarkup(getPos(), null, {
            ...node.attrs,
            width: $container.style.width || null,
          }));
        }
      };

      if (!editable) return { dom: $container };

      const iconStyle = 'width: 24px; height: 24px; cursor: pointer;';

      const createToolbar = () => {
        const $toolbar = document.createElement('div');
        $toolbar.setAttribute('style', `
          position: absolute;
          top: 10%; left: 50%;
          width: 100px;
          height: 25px;
          z-index: 999;
          background-color: rgba(255, 255, 255, 0.7);
          border-radius: 4px;
          border: 2px solid #6C6C6C;
          transform: translate(-50%, -50%);
          display: flex;
          justify-content: space-between;
          align-items: center;
          padding: 0 10px;
        `);

        const buttons = [
          { float: 'left', icon: 'https://fonts.gstatic.com/s/i/short-term/release/materialsymbolsoutlined/format_align_left/default/20px.svg' },
          { float: 'none', icon: 'https://fonts.gstatic.com/s/i/short-term/release/materialsymbolsoutlined/format_align_center/default/20px.svg' },
          { float: 'right', icon: 'https://fonts.gstatic.com/s/i/short-term/release/materialsymbolsoutlined/format_align_right/default/20px.svg' },
        ];

        buttons.forEach(({ float, icon }) => {
          const $btn = document.createElement('img');
          $btn.src = icon;
          $btn.setAttribute('style', iconStyle);
          $btn.addEventListener('mouseover', e => (e.target.style.opacity = '0.3'));
          $btn.addEventListener('mouseout', e => (e.target.style.opacity = '1'));
          $btn.addEventListener('click', () => {
            view.dispatch(view.state.tr.setNodeMarkup(getPos(), undefined, {
              ...node.attrs,
              float,
              width: $container.style.width || null,
            }));
          });
          $toolbar.appendChild($btn);
        });

        $container.appendChild($toolbar);
      };

      $container.addEventListener('click', () => {
        if ($container.childElementCount > 1) {
          Array.from($container.children).forEach((child, i) => {
            if (i > 0) $container.removeChild(child);
          });
        }

        createToolbar();

        const isMobile = document.documentElement.clientWidth < 768;
        const dotSize = isMobile ? 16 : 9;

        const positions = [
          { transform: 'translate(-50%, -50%)', top: '0%', left: '0%' },
          { transform: 'translate(50%, -50%)', top: '0%', right: '0%' },
          { transform: 'translate(-50%, 50%)', bottom: '0%', left: '0%' },
          { transform: 'translate(50%, 50%)', bottom: '0%', right: '0%' },
        ];

        let isResizing = false;
        let startX = 0;
        let startWidth = 0;

        const startResize = (clientX) => {
          isResizing = true;
          startX = clientX;
          startWidth = $container.offsetWidth;
        };

        const resize = (clientX, isLeft) => {
          if (!isResizing || !$container.parentElement) return;
          const deltaX = isLeft ? -(clientX - startX) : clientX - startX;
          const newWidthPx = startWidth + deltaX;
          const parentWidth = $container.parentElement.offsetWidth;
          const newWidthPercent = (newWidthPx / parentWidth) * 100;

          $container.style.width = `${newWidthPercent}%`;
        };

        const endResize = () => {
          isResizing = false;
          if ($container.parentElement) {
            const parentWidth = $container.parentElement.offsetWidth;
            const containerWidth = $container.offsetWidth;
            const finalWidth = (containerWidth / parentWidth) * 100;
            $container.style.width = `${finalWidth}%`;

            dispatchNodeView();
          }
        };

        positions.forEach((pos, index) => {
          const $dot = document.createElement('div');
          $dot.setAttribute('style', `
            position: absolute;
            width: ${dotSize}px;
            height: ${dotSize}px;
            border: 1.5px solid #6C6C6C;
            border-radius: 50%;
            background-color: white;
            cursor: ${index % 2 === 0 ? 'nwse-resize' : 'nesw-resize'};
            ${Object.entries(pos).map(([k, v]) => `${k}: ${v}`).join(';')}
          `);

          const isLeft = index === 0 || index === 2;

          $dot.addEventListener('mousedown', (e) => {
            e.preventDefault();
            startResize(e.clientX);
            const move = (e) => resize(e.clientX, isLeft);
            const up = () => {
              endResize();
              document.removeEventListener('mousemove', move);
              document.removeEventListener('mouseup', up);
            };
            document.addEventListener('mousemove', move);
            document.addEventListener('mouseup', up);
          });

          $dot.addEventListener('touchstart', (e) => {
            e.preventDefault();
            startResize(e.touches[0].clientX);
            const move = (e) => resize(e.touches[0].clientX, isLeft);
            const end = () => {
              endResize();
              document.removeEventListener('touchmove', move);
              document.removeEventListener('touchend', end);
            };
            document.addEventListener('touchmove', move, { passive: false });
            document.addEventListener('touchend', end);
          });

          $container.appendChild($dot);
        });
      });

      // Cleanup on outside click
      document.addEventListener('click', (e) => {
        if (!($container.contains(e.target as Node))) {
          const cleanStyle = $container.style.cssText.replace('border: 1px dashed #6C6C6C;', '');
          $container.setAttribute('style', cleanStyle);
          while ($container.childElementCount > 1) {
            $container.removeChild($container.lastChild as Node);
          }
        }
      });

      return { dom: $wrapper };
    };
  },
});
