#!/usr/bin/env python3
"""
公章图片优化工具
功能：去除白色背景、调整颜色、锐化
"""

import sys
from PIL import Image, ImageEnhance
import numpy as np

def optimize_seal(input_path, output_path=None):
    """
    优化公章图片
    
    Args:
        input_path: 输入图片路径
        output_path: 输出图片路径（可选）
    """
    if output_path is None:
        output_path = input_path.replace('.png', '_optimized.png')
    
    print(f"正在处理: {input_path}")
    
    # 1. 打开图片
    img = Image.open(input_path).convert('RGBA')
    print(f"✓ 图片尺寸: {img.size}")
    
    # 2. 转换为numpy数组
    data = np.array(img)
    
    # 3. 去除白色/浅色背景
    print("✓ 去除背景...")
    r, g, b, a = data[:,:,0], data[:,:,1], data[:,:,2], data[:,:,3]
    
    # 识别浅色像素（接近白色）
    light_pixels = (r > 200) & (g > 200) & (b > 200)
    
    # 设置为透明
    a[light_pixels] = 0
    data[:,:,3] = a
    
    # 4. 调整颜色为标准印章红
    print("✓ 调整颜色...")
    seal_red = (197, 48, 48)  # 标准印章红 #C53030
    
    # 识别有颜色的像素（非透明）
    colored_pixels = a > 0
    
    # 计算亮度
    brightness = (r[colored_pixels] * 0.299 + 
                  g[colored_pixels] * 0.587 + 
                  b[colored_pixels] * 0.114)
    
    # 根据原始亮度调整新颜色
    for i, (y, x) in enumerate(np.argwhere(colored_pixels)):
        brightness_ratio = brightness[i] / 255.0
        data[y, x, 0] = int(seal_red[0] * brightness_ratio)
        data[y, x, 1] = int(seal_red[1] * brightness_ratio)
        data[y, x, 2] = int(seal_red[2] * brightness_ratio)
    
    # 5. 转回图片
    img_optimized = Image.fromarray(data, 'RGBA')
    
    # 6. 锐化
    print("✓ 锐化处理...")
    enhancer = ImageEnhance.Sharpness(img_optimized)
    img_optimized = enhancer.enhance(2.0)
    
    # 7. 调整对比度
    print("✓ 增强对比度...")
    enhancer = ImageEnhance.Contrast(img_optimized)
    img_optimized = enhancer.enhance(1.3)
    
    # 8. 保存
    img_optimized.save(output_path, 'PNG')
    print(f"✓ 保存到: {output_path}")
    
    # 9. 显示对比
    print("\n优化结果:")
    print(f"  输入文件: {input_path}")
    print(f"  输出文件: {output_path}")
    print(f"  原始尺寸: {img.size}")
    print(f"  优化尺寸: {img_optimized.size}")
    
    import os
    input_size = os.path.getsize(input_path) / 1024
    output_size = os.path.getsize(output_path) / 1024
    print(f"  原始大小: {input_size:.1f} KB")
    print(f"  优化大小: {output_size:.1f} KB")
    
    return output_path

def resize_seal(img_path, target_size=800):
    """
    调整公章尺寸
    
    Args:
        img_path: 图片路径
        target_size: 目标尺寸（正方形）
    """
    img = Image.open(img_path)
    
    # 如果已经是目标尺寸，跳过
    if img.size[0] >= target_size and img.size[1] >= target_size:
        return img_path
    
    print(f"✓ 调整尺寸到 {target_size}x{target_size}...")
    
    # 计算新尺寸（保持宽高比）
    ratio = min(target_size / img.size[0], target_size / img.size[1])
    new_size = (int(img.size[0] * ratio), int(img.size[1] * ratio))
    
    # 缩放
    img_resized = img.resize(new_size, Image.Resampling.LANCZOS)
    
    # 保存
    output_path = img_path.replace('.png', f'_{target_size}px.png')
    img_resized.save(output_path, 'PNG')
    
    return output_path

def main():
    """主函数"""
    if len(sys.argv) < 2:
        print("用法: python3 optimize_seal.py <输入图片路径> [输出图片路径]")
        print("\n示例:")
        print("  python3 optimize_seal.py 对公帐号666.png")
        print("  python3 optimize_seal.py input.png output.png")
        sys.exit(1)
    
    input_path = sys.argv[1]
    output_path = sys.argv[2] if len(sys.argv) > 2 else None
    
    try:
        # 优化公章
        optimized_path = optimize_seal(input_path, output_path)
        
        print("\n✅ 优化完成！")
        print("\n建议:")
        print("  1. 对比查看原图和优化后的图片")
        print("  2. 如果尺寸太小，可以用 resize_seal() 放大")
        print("  3. 上传到公章管理系统测试效果")
        print(f"\n优化后的文件: {optimized_path}")
        
    except FileNotFoundError:
        print(f"❌ 错误: 找不到文件 {input_path}")
        sys.exit(1)
    except Exception as e:
        print(f"❌ 错误: {str(e)}")
        sys.exit(1)

if __name__ == '__main__':
    main()
